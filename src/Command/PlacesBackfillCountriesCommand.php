<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\ZipCity;
use App\Repository\EventRepository;
use App\Repository\PlaceRepository;
use App\Repository\ZipCityRepository;
use App\Utils\CityManipulator;
use App\Utils\SluggerUtils;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * One-off repair for places imported without a country (and therefore without a city)
 * because their feed's country code did not resolve at import time.
 *
 * The postal code is the evidence: every zip_city row of the place's postal code names
 * a country, and when they all agree that country is set. The city is set too when one
 * of those rows carries the place's city name. Events of a repaired place get their
 * denormalised place_country_id and are re-indexed, since the search documents embed
 * the place's city and country. Places whose postal code is unknown or shared between
 * countries (Belgian and Swiss 4-digit codes overlap) are left untouched and counted.
 */
#[AsCommand(
    name: 'app:places:backfill-countries',
    description: 'Set the country (and city when the name matches) of places stored without one, from their postal code (preview with --dry-run)',
)]
final class PlacesBackfillCountriesCommand extends Command
{
    private const int PLACES_BATCH_SIZE = 200;

    private const int EVENTS_BATCH_SIZE = 200;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlaceRepository $placeRepository,
        private readonly ZipCityRepository $zipCityRepository,
        private readonly EventRepository $eventRepository,
        private readonly CityManipulator $cityManipulator,
        #[Autowire(service: 'fos_elastica.object_persister.event')]
        private readonly ObjectPersisterInterface $eventPersister,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be repaired without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $io->title($dryRun ? 'Places without country (preview)' : 'Backfilling place countries');

        /** @var int[] $placeIds */
        $placeIds = $this
            ->placeRepository
            ->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.country IS NULL')
            ->andWhere("p.cityPostalCode IS NOT NULL AND p.cityPostalCode <> ''")
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        $stats = ['repaired' => 0, 'with city' => 0, 'unknown postal code' => 0, 'ambiguous postal code' => 0, 'events updated' => 0];
        $io->progressStart(\count($placeIds));

        foreach (array_chunk($placeIds, self::PLACES_BATCH_SIZE) as $chunkIds) {
            /** @var Place[] $places */
            $places = $this->placeRepository->findBy(['id' => $chunkIds]);
            $zipCitiesByPostalCode = $this->loadZipCities($places);
            $previewedPlaceIds = [];

            foreach ($places as $place) {
                $zipCities = $zipCitiesByPostalCode[(string) $place->getCityPostalCode()] ?? [];
                if ([] === $zipCities) {
                    ++$stats['unknown postal code'];
                    continue;
                }

                $country = self::singleCountry($zipCities);
                if (null === $country) {
                    ++$stats['ambiguous postal code'];
                    continue;
                }

                $city = $this->matchCity($zipCities, $place->getCityName());
                ++$stats['repaired'];
                if (null !== $city) {
                    ++$stats['with city'];
                }

                if ($dryRun) {
                    $previewedPlaceIds[] = (int) $place->getId();
                    continue;
                }

                $place->setCountry($country);
                if (null !== $city) {
                    $place->setCity($city);
                }

                $this->entityManager->flush();
                $stats['events updated'] += $this->propagateToEvents($place, $country);
            }

            if ([] !== $previewedPlaceIds) {
                $stats['events updated'] += $this->countEventsWithoutCountry($previewedPlaceIds);
            }

            $this->entityManager->clear();
            $io->progressAdvance(\count($chunkIds));
        }

        $io->progressFinish();
        $io->table(['Outcome', 'Places'], array_map(static fn (string $label, int $count): array => [$label, $count], array_keys($stats), $stats));

        if ($dryRun) {
            $io->note('Nothing was written. Run again without --dry-run to apply.');
        } else {
            $io->success(\sprintf('%d places repaired.', $stats['repaired']));
        }

        return Command::SUCCESS;
    }

    /**
     * @param Place[] $places
     *
     * @return array<string, ZipCity[]> zip_city rows grouped by postal code
     */
    private function loadZipCities(array $places): array
    {
        $postalCodes = array_values(array_unique(array_map(
            static fn (Place $place): string => (string) $place->getCityPostalCode(),
            $places,
        )));

        /** @var ZipCity[] $zipCities */
        $zipCities = $this
            ->zipCityRepository
            ->createQueryBuilder('z')
            ->addSelect('country', 'parent')
            ->join('z.country', 'country')
            ->leftJoin('z.parent', 'parent')
            ->where('z.postalCode IN (:postalCodes)')
            ->setParameter('postalCodes', $postalCodes)
            ->getQuery()
            ->getResult();

        $byPostalCode = [];
        foreach ($zipCities as $zipCity) {
            $byPostalCode[(string) $zipCity->getPostalCode()][] = $zipCity;
        }

        return $byPostalCode;
    }

    /**
     * The country every row of the postal code agrees on, or null when they disagree.
     *
     * @param ZipCity[] $zipCities
     */
    private static function singleCountry(array $zipCities): ?Country
    {
        $countries = [];
        foreach ($zipCities as $zipCity) {
            $country = $zipCity->getCountry();
            if (null !== $country) {
                $countries[(string) $country->getId()] = $country;
            }
        }

        return 1 === \count($countries) ? reset($countries) : null;
    }

    /**
     * The city of the zip_city row whose name is the place's city name, spelling
     * differences aside ("St" / "Saint", accents, case, hyphens).
     *
     * @param ZipCity[] $zipCities
     */
    private function matchCity(array $zipCities, ?string $cityName): ?City
    {
        if (null === $cityName || '' === trim($cityName)) {
            return null;
        }

        $wanted = array_map(SluggerUtils::generateSlug(...), $this->cityManipulator->getCityNameAlternatives($cityName));

        foreach ($zipCities as $zipCity) {
            $parent = $zipCity->getParent();
            if (null !== $parent && \in_array(SluggerUtils::generateSlug((string) $zipCity->getName()), $wanted, true)) {
                return $parent;
            }
        }

        return null;
    }

    /**
     * How many events the repair of these places would touch (preview only).
     *
     * @param int[] $placeIds
     */
    private function countEventsWithoutCountry(array $placeIds): int
    {
        return (int) $this
            ->eventRepository
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.place IN (:places)')
            ->andWhere('e.placeCountry IS NULL')
            ->setParameter('places', $placeIds)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Fill the denormalised country of the place's events and refresh their search
     * documents. Events that already carried the country only need the re-index (their
     * document embeds the place's city, which may just have been set).
     *
     * @return int number of events whose country was filled
     */
    private function propagateToEvents(Place $place, Country $country): int
    {
        $updated = 0;
        $lastId = 0;

        while (true) {
            /** @var Event[] $events */
            $events = $this
                ->eventRepository
                ->createQueryBuilder('e')
                ->where('e.place = :place')
                ->andWhere('e.id > :lastId')
                ->setParameter('place', $place)
                ->setParameter('lastId', $lastId)
                ->orderBy('e.id', 'ASC')
                ->setMaxResults(self::EVENTS_BATCH_SIZE)
                ->getQuery()
                ->getResult();

            if ([] === $events) {
                return $updated;
            }

            $untouched = [];
            foreach ($events as $event) {
                $lastId = (int) $event->getId();
                if (null === $event->getPlaceCountry()) {
                    $event->setPlaceCountry($country);
                    ++$updated;
                } else {
                    $untouched[] = $event;
                }
            }

            // The Doctrine listener re-indexes the updated events on flush
            $this->entityManager->flush();

            if ([] !== $untouched) {
                $this->eventPersister->replaceMany($untouched);
            }
        }
    }
}
