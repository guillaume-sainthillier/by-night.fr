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
use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Entity\City;
use App\Entity\Place;
use App\Repository\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
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
 * Moves the places the import filed under the wrong namesake of their city.
 *
 * Until CityComparator used the postal code, the first city of the name the database
 * returned won: Pau-le-hameau (Savoie) got the venues of Pau (Pyrénées-Atlantiques), Le
 * Havre and La Rochelle those of their cities. Each place on a city that has namesakes in
 * its country is resolved again as an import would now resolve it, from its postal code, and
 * moved, with its name slugs, when the winning namesake agrees better with that postal code
 * (between two cities it contradicts alike, a move would only be churn). The events of a moved place
 * are re-indexed once each chunk is flushed (EventDocumentRefreshListener). Previews by
 * default, writes with --apply.
 */
#[AsCommand('app:places:fix-namesake-cities', 'Move the places filed under the wrong namesake of their city (preview by default, --apply to write)')]
final class PlacesFixNamesakeCitiesCommand extends Command
{
    private const int BATCH_SIZE = 500;

    private const int PREVIEW_SAMPLE = 20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CityRepository $cityRepository,
        private readonly CityComparator $cityComparator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the moves. Without it the command only prints what it would do');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');

        $pagination = $this->createPagination();
        $total = \count($pagination);
        $io->section(\sprintf('%s %d place(s) on a city with namesakes', $apply ? 'Fixing' : 'Previewing', $total));

        $moved = 0;
        $sample = [];
        $io->progressStart($total);
        foreach ($pagination->getChunkResults() as $chunk) {
            foreach ($this->process($chunk) as [$place, $from, $to]) {
                ++$moved;
                if (\count($sample) < self::PREVIEW_SAMPLE) {
                    $sample[] = [$place->getId(), $place->getName(), $place->getCityPostalCode(), $this->describe($from), $this->describe($to)];
                }
            }

            if ($apply) {
                $this->entityManager->flush();
            }

            $this->entityManager->clear();
            $io->progressAdvance(\count($chunk));
        }

        $io->progressFinish();
        $io->table(['Id', 'Place', 'Postal code', 'From', 'To'], $sample);

        if ($apply) {
            $io->success(\sprintf('%d place(s) moved, their events are being re-indexed.', $moved));
        } else {
            $io->note(\sprintf('%d place(s) would move. Preview only, nothing was written: re-run with --apply.', $moved));
        }

        return Command::SUCCESS;
    }

    /**
     * The places on a city with namesakes in its country, by chunks. Walked by id, from the
     * last one of the previous chunk: the moves of a chunk never shift the next one.
     *
     * @return CursorPagination<Place>
     */
    private function createPagination(): CursorPagination
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(Place::class)
            ->createQueryBuilder('p')
            ->join('p.city', 'c')
            ->where('p.cityPostalCode IS NOT NULL')
            ->andWhere(\sprintf('EXISTS (SELECT o.id FROM %s o WHERE o.country = c.country AND o.name = c.name AND o.id <> c.id)', City::class));

        return new CursorPagination(
            $queryBuilder,
            new OrderConfigurations(new OrderConfiguration('p.id', static fn (Place $place): ?int => $place->getId())),
            self::BATCH_SIZE,
            fetchJoinCollection: false,
        );
    }

    /**
     * Resolves the chunk's places again and moves those another namesake wins.
     *
     * @param Place[] $places
     *
     * @return iterable<array{Place, City, City}>
     */
    private function process(array $places): iterable
    {
        $dtos = [];
        foreach ($places as $place) {
            $dtos[(int) $place->getId()] = $this->toDto($place);
        }

        $candidates = $this->cityRepository->findAllByDtos(array_values(array_filter($dtos)), false);

        foreach ($places as $place) {
            $from = $place->getCity();
            $dto = $dtos[(int) $place->getId()];
            if (null === $from || null === $dto) {
                continue;
            }

            $to = $this->cityComparator->getMostMatching($candidates, $dto)?->getEntity();
            if (!$to instanceof City || $to->getId() === $from->getId()) {
                continue;
            }

            // Only for a namesake the postal code agrees with better: between two cities it
            // contradicts alike (a Réunion code on Saint-Denis), moving would only be churn
            if ($this->cityComparator->getPostalCodeAgreement($to, $dto->postalCode) <= $this->cityComparator->getPostalCodeAgreement($from, $dto->postalCode)) {
                continue;
            }

            $place->setCity($to);
            foreach ($place->getNameSlugs() as $nameSlug) {
                if ($nameSlug->getCity()?->getId() === $from->getId()) {
                    $nameSlug->setCity($to);
                }
            }

            yield [$place, $from, $to];
        }
    }

    /**
     * The place's city as the import would look it up: the namesakes of its current city,
     * told apart by the place's postal code.
     */
    private function toDto(Place $place): ?CityDto
    {
        $city = $place->getCity();
        $countryId = $city?->getCountry()?->getId();
        if (null === $city || null === $countryId) {
            return null;
        }

        $country = new CountryDto();
        $country->entityId = $countryId;

        $dto = new CityDto();
        $dto->name = $city->getName();
        $dto->postalCode = $place->getCityPostalCode();
        $dto->country = $country;

        return $dto;
    }

    private function describe(City $city): string
    {
        return \sprintf('%s (%s, #%d)', $city->getName(), $city->getAdmin2Code() ?? '?', $city->getId());
    }
}
