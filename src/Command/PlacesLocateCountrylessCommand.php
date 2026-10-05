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
use App\Entity\Place;
use App\Repository\CityRepository;
use App\Repository\CountryRepository;
use App\Repository\EventRepository;
use App\Repository\PlaceRepository;
use App\Utils\CityManipulator;
use App\Utils\SluggerUtils;
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
 * One-off repair of the places stored without a country, before the ones left are deleted
 * (app:events:remove-countryless): their events had no city or country page and answered on
 * "/unknown". The postal codes were tried in September (app:places:backfill-countries); what
 * is left mostly has none, but nearly every place has coordinates.
 *
 * The coordinates only point at the cities around the place: the cities include hamlets and
 * the quarters of Paris, so the nearest one is no evidence on its own (the Maison de la Radio
 * is nearest to "Grenelle"). A city is given when a name confirms it, by order of strength:
 * 1. the place's town is the name of a city at most 20 km away;
 * 2. the place's town starts the name of a city at most 10 km away, or the other way round
 *    ("La Baule" for La Baule-Escoublac), the most populated one;
 * 3. the place's own name holds the name of a city at most 10 km away ("Place Jean Jaurès,
 *    Castres (81)"), the longest name.
 * Without such a name, a city at most 3 km away only gives its country: the place leaves
 * "/unknown" for its country's pages. So does a place whose events name several towns: it was
 * merged across them by older imports, and app:places:fix-cityless splits it once it has a
 * country. The other places are abroad (or have no coordinates) and are left for deletion.
 *
 * A place whose city already holds a place of its name is the same venue imported again: its
 * events move there and it is left empty (app:places:remove-eventless). Each flush re-indexes
 * the events of the places it changed. Previews by default, writes with --apply.
 */
#[AsCommand('app:places:locate-countryless', 'Give the places without a country the city or country their coordinates and names designate (preview by default, --apply to write)')]
final class PlacesLocateCountrylessCommand extends Command
{
    private const int BATCH_SIZE = 200;

    private const int PREVIEW_SAMPLE = 10;

    /** Size of the cells the cities are filed in, in degrees */
    private const float CELL = 0.1;

    private const float NAMED_CITY_KM = 20.0;

    private const float NEARBY_CITY_KM = 10.0;

    private const float COUNTRY_ONLY_KM = 3.0;

    /** Shorter names match too much: "Sées", "Eu" */
    private const int MIN_NAME_LENGTH = 4;

    private const string NAMED = 'Town is a city';

    private const string PREFIXED = 'Town starts a city name';

    private const string IN_NAME = 'City in the place name';

    private const string COUNTRY_ONLY = 'Country only';

    private const string MERGED = 'Country only, merged across towns';

    private const string UNLOCATED = 'Left: no city close enough (abroad)';

    private const string NO_COORDINATES = 'Left: no coordinates';

    /** @var array<int, array<int, list<array{id: int, country: string, name: string, slug: string, latitude: float, longitude: float, population: int}>>> */
    private array $grid = [];

    /** @var array<string, Place> the places the current chunk gave a city, by city and slug: not flushed yet, the twin lookup misses them */
    private array $located = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlaceRepository $placeRepository,
        private readonly CityRepository $cityRepository,
        private readonly CountryRepository $countryRepository,
        private readonly EventRepository $eventRepository,
        private readonly CityManipulator $cityManipulator,
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

        $this->loadCities();

        $places = new CursorPagination(
            $this->placeRepository->createQueryBuilder('p')->where('p.country IS NULL'),
            new OrderConfigurations(new OrderConfiguration('p.id', static fn (Place $place): ?int => $place->getId())),
            self::BATCH_SIZE,
            fetchJoinCollection: false,
        );
        $total = \count($places);
        $io->section(\sprintf('%s %d place(s) without a country', $apply ? 'Locating' : 'Previewing', $total));

        /** @var array<string, array{places: int, events: int}> $totals */
        $totals = [];
        foreach ([self::NAMED, self::PREFIXED, self::IN_NAME, self::COUNTRY_ONLY, self::MERGED, self::UNLOCATED, self::NO_COORDINATES] as $outcome) {
            $totals[$outcome] = ['places' => 0, 'events' => 0];
        }

        $twins = ['places' => 0, 'events' => 0];
        /** @var array<string, list<string>> $samples */
        $samples = [];
        $io->progressStart($total);
        foreach ($places->getChunkResults() as $chunk) {
            $eventCounts = $this->countEvents($chunk);
            $merged = $this->findMergedAcrossTowns($chunk);

            foreach ($chunk as $place) {
                $placeId = (int) $place->getId();
                [$outcome, $match] = $this->locate($place, isset($merged[$placeId]));
                ++$totals[$outcome]['places'];
                $totals[$outcome]['events'] += $eventCounts[$placeId] ?? 0;

                if (null !== $match && \count($samples[$outcome] ?? []) < self::PREVIEW_SAMPLE) {
                    $samples[$outcome][] = \sprintf('#%d %s (%s) → %s, %s, %.1f km', $placeId, $place->getName(), $place->getCityName() ?? '-', $match['name'], $match['country'], $match['distance']);
                }

                if (null === $match) {
                    continue;
                }

                $countryOnly = \in_array($outcome, [self::COUNTRY_ONLY, self::MERGED], true);
                $twin = $countryOnly ? null : $this->findTwin($place, $match['id']);
                if (null !== $twin) {
                    ++$twins['places'];
                    $twins['events'] += $eventCounts[$placeId] ?? 0;
                }

                if ($apply) {
                    null === $twin ? $this->setLocation($place, $match, $countryOnly) : $this->moveEvents($place, $twin);
                }
            }

            // One flush per chunk: it commits on its own, and the re-indexing it triggers goes
            // out after the commit
            if ($apply) {
                $this->entityManager->flush();
            }

            $this->entityManager->clear();
            $this->located = [];
            $io->progressAdvance(\count($chunk));
        }

        $io->progressFinish();
        $io->table(['Outcome', 'Places', 'Events'], array_map(
            static fn (string $outcome, array $count): array => [$outcome, $count['places'], $count['events']],
            array_keys($totals),
            $totals,
        ));

        $io->text(\sprintf('Of the places given a city, %d already had their venue there: their %d event(s) move to it, they are left empty (app:places:remove-eventless).', $twins['places'], $twins['events']));

        foreach ($samples as $outcome => $lines) {
            $io->text(\sprintf('<info>%s</info>, for instance:', $outcome));
            $io->listing($lines);
        }

        if ($apply) {
            $io->success('Done. Run app:places:fix-cityless, then app:events:remove-countryless.');
        } else {
            $io->note('Preview only, nothing was written. Re-run with --apply.');
        }

        return Command::SUCCESS;
    }

    /**
     * Files every city with coordinates in cells of CELL degrees: a place only looks at the
     * cells around it.
     */
    private function loadCities(): void
    {
        /** @var list<array{id: int, countryId: string|null, name: string|null, latitude: float|null, longitude: float|null, population: int|null}> $rows */
        $rows = $this
            ->entityManager
            ->createQueryBuilder()
            ->select('c.id', 'IDENTITY(c.country) AS countryId', 'c.name', 'c.latitude', 'c.longitude', 'c.population')
            ->from(City::class, 'c')
            ->where('c.latitude IS NOT NULL AND c.longitude IS NOT NULL AND c.country IS NOT NULL')
            ->getQuery()
            ->getArrayResult();

        $this->grid = [];
        foreach ($rows as $row) {
            $latitude = (float) $row['latitude'];
            $longitude = (float) $row['longitude'];
            $this->grid[self::cell($latitude)][self::cell($longitude)][] = [
                'id' => (int) $row['id'],
                'country' => (string) $row['countryId'],
                'name' => (string) $row['name'],
                'slug' => SluggerUtils::generateSlug((string) $row['name']),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'population' => (int) $row['population'],
            ];
        }
    }

    /**
     * @return array{0: string, 1: array{id: int, name: string, country: string, distance: float}|null}
     */
    private function locate(Place $place, bool $mergedAcrossTowns): array
    {
        $latitude = $place->getLatitude();
        $longitude = $place->getLongitude();
        // 0,0 is the default of some feeds, not a place in the Gulf of Guinea
        if (null === $latitude || null === $longitude || (0.0 === $latitude && 0.0 === $longitude)) {
            return [self::NO_COORDINATES, null];
        }

        $towns = null === $place->getCityName() || '' === trim($place->getCityName())
            ? []
            : array_values(array_unique(array_map(SluggerUtils::generateSlug(...), $this->cityManipulator->getCityNameAlternatives($place->getCityName()))));
        $placeName = '-' . SluggerUtils::generateSlug((string) $place->getName()) . '-';

        $nearest = $named = $prefixed = $inName = null;
        foreach ($this->citiesAround($latitude, $longitude) as $city) {
            $distance = self::distance($latitude, $longitude, $city['latitude'], $city['longitude']);
            $candidate = [...$city, 'distance' => $distance];

            if (null === $nearest || $distance < $nearest['distance']) {
                $nearest = $candidate;
            }

            if ($distance <= self::NAMED_CITY_KM && \in_array($city['slug'], $towns, true)
                && (null === $named || $distance < $named['distance'])) {
                $named = $candidate;
            }

            if ($distance > self::NEARBY_CITY_KM || \strlen($city['slug']) < self::MIN_NAME_LENGTH) {
                continue;
            }

            if (self::startsTheOther($city['slug'], $towns)
                && (null === $prefixed || $city['population'] > $prefixed['population'])) {
                $prefixed = $candidate;
            }

            if (str_contains($placeName, '-' . $city['slug'] . '-')
                && (null === $inName || \strlen($city['slug']) > \strlen($inName['slug'])
                    || (\strlen($city['slug']) === \strlen($inName['slug']) && $distance < $inName['distance']))) {
                $inName = $candidate;
            }
        }

        [$outcome, $match] = match (true) {
            null !== $named => [self::NAMED, $named],
            null !== $prefixed => [self::PREFIXED, $prefixed],
            null !== $inName => [self::IN_NAME, $inName],
            null !== $nearest && $nearest['distance'] <= self::COUNTRY_ONLY_KM => [self::COUNTRY_ONLY, $nearest],
            default => [self::UNLOCATED, null],
        };

        if (null === $match) {
            return [$outcome, null];
        }

        if ($mergedAcrossTowns) {
            $outcome = self::MERGED;
        }

        return [$outcome, ['id' => $match['id'], 'name' => $match['name'], 'country' => $match['country'], 'distance' => $match['distance']]];
    }

    /**
     * @return iterable<array{id: int, country: string, name: string, slug: string, latitude: float, longitude: float, population: int}>
     */
    private function citiesAround(float $latitude, float $longitude): iterable
    {
        // NAMED_CITY_KM around the place: 0.2° of latitude, 0.3° of longitude in France
        $row = self::cell($latitude);
        $column = self::cell($longitude);
        for ($y = $row - 2; $y <= $row + 2; ++$y) {
            for ($x = $column - 3; $x <= $column + 3; ++$x) {
                yield from $this->grid[$y][$x] ?? [];
            }
        }
    }

    /**
     * One of the town's spellings starts the city's name, or the city's name starts it, on a
     * word boundary.
     *
     * @param list<string> $towns
     */
    private static function startsTheOther(string $citySlug, array $towns): bool
    {
        foreach ($towns as $town) {
            if (\strlen($town) >= self::MIN_NAME_LENGTH
                && (str_starts_with($citySlug, $town . '-') || str_starts_with($town, $citySlug . '-'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The same venue, imported again since in its city.
     */
    private function findTwin(Place $place, int $cityId): ?Place
    {
        $key = $cityId . '|' . $place->getSlug();
        $twin = $this->located[$key] ?? $this->placeRepository->findOneBy(['city' => $cityId, 'slug' => $place->getSlug()]);
        if (null !== $twin && $twin->getId() !== $place->getId()) {
            return $twin;
        }

        $this->located[$key] = $place;

        return null;
    }

    /**
     * @param array{id: int, name: string, country: string, distance: float} $match
     */
    private function setLocation(Place $place, array $match, bool $countryOnly): void
    {
        /** @var Country $country */
        $country = $this->countryRepository->find($match['country']);
        $city = $countryOnly ? null : $this->cityRepository->find($match['id']);

        $place->setCountry($country)->setCity($city);
        foreach ($place->getNameSlugs() as $nameSlug) {
            $nameSlug->setCountry($nameSlug->getCountry() ?? $country);
            if (null !== $city) {
                $nameSlug->setCity($nameSlug->getCity() ?? $city);
            }
        }

        // The events' copy of their venue's country
        foreach ($this->eventRepository->findBy(['place' => $place]) as $event) {
            $event->setPlaceCountry($country);
        }
    }

    private function moveEvents(Place $place, Place $twin): void
    {
        foreach ($this->eventRepository->findBy(['place' => $place]) as $event) {
            $event->setPlace($twin)->setPlaceCountry($twin->getCountry());
        }
    }

    /**
     * @param Place[] $places
     *
     * @return array<int, int> the number of events by place id
     */
    private function countEvents(array $places): array
    {
        /** @var list<array{place: int, events: int}> $rows */
        $rows = $this
            ->eventRepository
            ->createQueryBuilder('e')
            ->select('IDENTITY(e.place) AS place', 'COUNT(e.id) AS events')
            ->where('e.place IN (:places)')
            ->setParameter('places', $places)
            ->groupBy('e.place')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'events', 'place');
    }

    /**
     * The places whose events carry several postal codes: older imports merged venues of one
     * name across towns.
     *
     * @param Place[] $places
     *
     * @return array<int, true>
     */
    private function findMergedAcrossTowns(array $places): array
    {
        /** @var list<int> $ids */
        $ids = $this
            ->eventRepository
            ->createQueryBuilder('e')
            ->select('IDENTITY(e.place)')
            ->where('e.place IN (:places)')
            ->andWhere("e.placePostalCode IS NOT NULL AND e.placePostalCode <> ''")
            ->setParameter('places', $places)
            ->groupBy('e.place')
            ->having('COUNT(DISTINCT e.placePostalCode) > 1')
            ->getQuery()
            ->getSingleColumnResult();

        return array_fill_keys(array_map(intval(...), $ids), true);
    }

    private static function cell(float $degrees): int
    {
        return (int) floor($degrees / self::CELL);
    }

    /**
     * In km, flat: exact enough over a few tens of kilometres.
     */
    private static function distance(float $latitude1, float $longitude1, float $latitude2, float $longitude2): float
    {
        $x = deg2rad($longitude2 - $longitude1) * cos(deg2rad(($latitude1 + $latitude2) / 2));
        $y = deg2rad($latitude2 - $latitude1);

        return 6371 * sqrt($x * $x + $y * $y);
    }
}
