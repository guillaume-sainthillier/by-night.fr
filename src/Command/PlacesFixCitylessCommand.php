<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Comparator\CityComparator;
use App\Comparator\PlaceComparator;
use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\PlaceDto;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\PlaceNameSlug;
use App\Repository\CityRepository;
use App\Repository\PlaceRepository;
use App\Utils\PlaceNameNormalizer;
use App\Utils\SluggerUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Repairs the places without a city, which older imports filed badly.
 *
 * 1. Split: compared by name alone, city-less places of one name were merged across the
 *    country (#99101 "Salle des fêtes" held the events of 41 départements). Each event still
 *    carries the postal code and town its source gave: the events of another town than the
 *    place's move to the place of that town, found or created. The place's source identities
 *    cannot be told apart, so they are dropped: the next import attaches each one again to the
 *    place of its town (PlaceComparator now compares towns).
 * 2. Locate: a city-less place gets the city its town and postal code designate, as an import
 *    would now resolve it (CityComparator), when the postal code confirms it (the city's own or
 *    of its département) and no place of that name is already in that city.
 *
 * Moved events and located places are re-indexed as each flush commits. Previews by default,
 * writes with --apply.
 */
#[AsCommand('app:places:fix-cityless', 'Split the city-less places merged across towns and give them their city (preview by default, --apply to write)')]
final class PlacesFixCitylessCommand extends Command
{
    private const int BATCH_SIZE = 500;

    /** A split flushes place by place: smaller chunks keep the unit of work light */
    private const int SPLIT_BATCH_SIZE = 50;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlaceRepository $placeRepository,
        private readonly CityRepository $cityRepository,
        private readonly CityComparator $cityComparator,
        private readonly PlaceComparator $placeComparator,
        private readonly PlaceNameNormalizer $placeNameNormalizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the changes. Without it the command only prints what it would do');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');

        $merged = $this->paginate($this->createMergedPlacesQueryBuilder(), self::SPLIT_BATCH_SIZE);
        $total = \count($merged);
        $io->section(\sprintf('1. %s %d city-less place(s) holding events of several towns', $apply ? 'Splitting' : 'Previewing', $total));
        $split = ['events' => 0, 'created' => 0, 'reused' => 0, 'identities' => 0];
        $io->progressStart($total);
        foreach ($merged->getChunkResults() as $chunk) {
            foreach ($chunk as $place) {
                foreach ($this->split($place) as $key => $count) {
                    $split[$key] += $count;
                }

                // Flushed place by place: the next split must find the places this one created
                if ($apply) {
                    $this->entityManager->flush();
                }

                $io->progressAdvance();
            }

            $this->entityManager->clear();
        }

        $io->progressFinish();
        $io->table(['Events moved', 'Places created', 'Existing places reused', 'Identities dropped'], [[$split['events'], $split['created'], $split['reused'], $split['identities']]]);

        $cityless = $this->paginate($this->createCitylessPlacesQueryBuilder(), self::BATCH_SIZE);
        $total = \count($cityless);
        $io->section(\sprintf('2. %s %d city-less place(s) with a postal code', $apply ? 'Locating' : 'Previewing', $total));
        $located = ['located' => 0, 'taken' => 0, 'unconfirmed' => 0];
        $io->progressStart($total);
        foreach ($cityless->getChunkResults() as $chunk) {
            foreach ($this->locate($chunk) as $key => $count) {
                $located[$key] += $count;
            }

            // One flush per chunk: it commits on its own, and the re-indexing it triggers goes
            // out after the commit
            if ($apply) {
                $this->entityManager->flush();
            }

            $this->entityManager->clear();
            $io->progressAdvance(\count($chunk));
        }

        $io->progressFinish();
        $io->table(['Located', 'Left: a place of that name is already there', 'Left: postal code does not confirm'], [[$located['located'], $located['taken'], $located['unconfirmed']]]);

        if ($apply) {
            $io->success('Done, the events concerned are being re-indexed.');
        } else {
            $io->note('Preview only, nothing was written. Re-run with --apply.');
        }

        return Command::SUCCESS;
    }

    /**
     * Walked by id, from the last place of the previous chunk: a place the chunk splits or
     * locates leaves the query, which would shift page numbers past the places after it.
     *
     * @return CursorPagination<Place>
     */
    private function paginate(QueryBuilder $queryBuilder, int $batchSize): CursorPagination
    {
        return new CursorPagination(
            $queryBuilder,
            new OrderConfigurations(new OrderConfiguration('p.id', static fn (Place $place): ?int => $place->getId())),
            $batchSize,
            fetchJoinCollection: false,
        );
    }

    /**
     * The city-less places whose events carry several postal codes.
     */
    private function createMergedPlacesQueryBuilder(): QueryBuilder
    {
        return $this
            ->placeRepository
            ->createQueryBuilder('p')
            ->where(\sprintf(
                "p.id IN (SELECT IDENTITY(e.place) FROM %s e JOIN e.place ep WHERE ep.city IS NULL AND e.placePostalCode IS NOT NULL AND e.placePostalCode <> '' GROUP BY e.place HAVING COUNT(DISTINCT e.placePostalCode) > 1)",
                Event::class,
            ));
    }

    /**
     * @return array{events: int, created: int, reused: int, identities: int}
     */
    private function split(Place $place): array
    {
        $result = ['events' => 0, 'created' => 0, 'reused' => 0, 'identities' => 0];

        // By town, as its source wrote it: events without a postal code stay where they are
        $towns = [];
        foreach ($this->entityManager->getRepository(Event::class)->findBy(['place' => $place]) as $event) {
            $postalCode = $event->getPlacePostalCode();
            if (null === $postalCode || '' === $postalCode) {
                continue;
            }

            $towns[$this->townKey($postalCode, $event->getPlaceCity())][] = $event;
        }

        if (\count($towns) < 2) {
            return $result;
        }

        // The place stays in the town its address names, else in the one of most events
        $ownTown = $this->townKey((string) $place->getCityPostalCode(), $place->getCityName());
        if (!isset($towns[$ownTown])) {
            uasort($towns, static fn (array $a, array $b): int => \count($b) <=> \count($a));
            $ownTown = (string) array_key_first($towns);
            $first = $towns[$ownTown][0];
            $place
                ->setCityPostalCode($first->getPlacePostalCode())
                ->setCityName($first->getPlaceCity())
                ->setStreet($first->getPlaceStreet());
        }

        foreach ($towns as $town => $events) {
            if ($town === $ownTown) {
                continue;
            }

            $target = $this->findPlaceInTown($place, $events[0]);
            if (null === $target) {
                $target = $this->createPlaceInTown($place, $events[0]);
                ++$result['created'];
            } else {
                ++$result['reused'];
            }

            foreach ($events as $event) {
                $event->setPlace($target);
                ++$result['events'];
            }
        }

        foreach ($place->getMetadatas()->toArray() as $metadata) {
            $place->removeMetadata($metadata);
            $this->entityManager->remove($metadata);
            ++$result['identities'];
        }

        return $result;
    }

    private function townKey(string $postalCode, ?string $town): string
    {
        return $postalCode . '|' . SluggerUtils::generateSlug($town ?? '');
    }

    /**
     * An existing city-less place of that name in the event's town (created by an earlier
     * split, or a record of its own), as an import would now find it.
     */
    private function findPlaceInTown(Place $place, Event $event): ?Place
    {
        $country = $place->getCountry();
        $slug = $this->placeNameNormalizer->normalize($place->getName(), $event->getPlaceCity());
        if (null === $country || null === $slug) {
            return null;
        }

        $candidates = array_filter(
            $this->placeRepository->findAllByNameSlugs([], [(string) $country->getId() => [$slug]]),
            static fn (Place $candidate): bool => $candidate->getId() !== $place->getId(),
        );

        $countryDto = new CountryDto();
        $countryDto->entityId = $country->getId();
        $cityDto = new CityDto();
        $cityDto->name = $event->getPlaceCity();
        $cityDto->postalCode = $event->getPlacePostalCode();
        $cityDto->country = $countryDto;
        $dto = new PlaceDto();
        $dto->name = $place->getName();
        $dto->street = $event->getPlaceStreet();
        $dto->city = $cityDto;
        $dto->country = $countryDto;

        $matching = $this->placeComparator->getMostMatching($candidates, $dto);
        $found = null !== $matching && $matching->getConfidence() >= 90.0 ? $matching->getEntity() : null;

        return $found instanceof Place ? $found : null;
    }

    private function createPlaceInTown(Place $place, Event $event): Place
    {
        $created = new Place()
            ->setName((string) $place->getName())
            ->setStreet($event->getPlaceStreet())
            ->setCityName($event->getPlaceCity())
            ->setCityPostalCode($event->getPlacePostalCode())
            ->setCountry($place->getCountry());

        $slug = $this->placeNameNormalizer->normalize($place->getName(), $event->getPlaceCity());
        if (null !== $slug) {
            $created->addNameSlug(new PlaceNameSlug()->setSlug($slug)->setCountry($place->getCountry()));
        }

        $this->entityManager->persist($created);

        return $created;
    }

    private function createCitylessPlacesQueryBuilder(): QueryBuilder
    {
        return $this
            ->placeRepository
            ->createQueryBuilder('p')
            ->where('p.city IS NULL')
            ->andWhere('p.country IS NOT NULL')
            ->andWhere("p.cityPostalCode IS NOT NULL AND p.cityPostalCode <> ''");
    }

    /**
     * @param Place[] $places
     *
     * @return array{located: int, taken: int, unconfirmed: int}
     */
    private function locate(array $places): array
    {
        $result = ['located' => 0, 'taken' => 0, 'unconfirmed' => 0];

        $dtos = [];
        foreach ($places as $place) {
            $countryDto = new CountryDto();
            $countryDto->entityId = $place->getCountry()?->getId();
            $dto = new CityDto();
            $dto->name = $place->getCityName();
            $dto->postalCode = $place->getCityPostalCode();
            $dto->country = $countryDto;
            $dtos[(int) $place->getId()] = $dto;
        }

        $candidates = $this->cityRepository->findAllByDtos(array_values($dtos), false);

        foreach ($places as $place) {
            $dto = $dtos[(int) $place->getId()];
            $city = $this->cityComparator->getMostMatching($candidates, $dto)?->getEntity();
            if (!$city instanceof City || $this->cityComparator->getPostalCodeAgreement($city, $dto->postalCode) < CityComparator::SAME_AREA) {
                ++$result['unconfirmed'];

                continue;
            }

            // A place of that name there already: moving this one would make two of it
            if (null !== $this->placeRepository->findOneBy(['city' => $city, 'slug' => $place->getSlug()])) {
                ++$result['taken'];

                continue;
            }

            $place->setCity($city);
            foreach ($place->getNameSlugs() as $nameSlug) {
                if (null === $nameSlug->getCity()) {
                    $nameSlug->setCity($city);
                }
            }

            ++$result['located'];
        }

        return $result;
    }
}
