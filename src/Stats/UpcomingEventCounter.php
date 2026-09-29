<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Stats;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\Place;
use App\Enum\AgendaType;
use App\Repository\EventRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stores the number of published events to come on every place, city and country, of each category in every city
 * and country (UpcomingCategory), and of each agenda type on every city and country (from the types
 * app:events:classify-agenda-types stored on the events), so the portals rank and count them with an indexed read
 * instead of grouping every event to come on each page.
 *
 * Run daily just after midnight by app:events:count-upcoming, when yesterday's events stop being "to come"; the
 * events imported during the day show in the counts the next night. An event created, changed or deleted on the site
 * (personal space, back office) is recounted right away, for its venues only (refreshPlaces(), see
 * UpcomingEventCountListener). The rows are written by DQL bulk updates: no lifecycle callback runs, so no updatedAt
 * changes and nothing is reindexed.
 *
 * The counts are read by plain SELECTs, then only the changed rows are written, by id. A single UPDATE joined to the
 * counts (a CTE) is no faster: it share-locks every event to come while it runs, and the parser worker then waits on
 * (or deadlocks with) it, and locks every venue it reads, changed or not.
 */
final readonly class UpcomingEventCounter
{
    private const int BATCH_SIZE = 1_000;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
    ) {
    }

    /**
     * Returns the number of rows whose count changed, of category counts stored, and of cities and countries whose
     * type counts changed.
     *
     * @return array{countries: int, cities: int, places: int, categories: int, types: int}
     */
    public function refresh(): array
    {
        // The venues from the events, then the cities and countries from the venues just stored
        $places = $this->store(Place::class, $this->eventRepository->countUpcomingByPlace());

        return [
            'countries' => $this->store(Country::class, $this->sumPlacesBy('country')),
            'cities' => $this->store(City::class, $this->sumPlacesBy('city')),
            'places' => $places,
            'categories' => $this->storeCategories(),
            'types' => $this->storeAgendaTypes(),
        ];
    }

    /**
     * Recounts these venues, their cities and countries, and the category and type counts of those cities and
     * countries: what a change to the events of these venues can move. The other rows keep the counts they have.
     * Returns the number of rows whose count changed, of category counts stored, and of cities and countries whose
     * type counts changed.
     *
     * @param list<int> $placeIds
     *
     * @return array{countries: int, cities: int, places: int, categories: int, types: int}
     */
    public function refreshPlaces(array $placeIds): array
    {
        if ([] === $placeIds) {
            return ['countries' => 0, 'cities' => 0, 'places' => 0, 'categories' => 0, 'types' => 0];
        }

        $zones = $this
            ->entityManager
            ->createQueryBuilder()
            ->select('IDENTITY(p.city) AS city', 'IDENTITY(p.country) AS country')
            ->from(Place::class, 'p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $placeIds)
            ->getQuery()
            ->getScalarResult();
        $cityIds = self::ids(array_column($zones, 'city'));
        $countryIds = self::ids(array_column($zones, 'country'));

        $places = $this->store(Place::class, $this->eventRepository->countUpcomingByPlace($placeIds), $placeIds);

        return [
            'countries' => [] === $countryIds ? 0 : $this->store(Country::class, $this->sumPlacesBy('country', $countryIds), $countryIds),
            'cities' => [] === $cityIds ? 0 : $this->store(City::class, $this->sumPlacesBy('city', $cityIds), $cityIds),
            'places' => $places,
            'categories' => $this->storeCategories($cityIds, $countryIds),
            'types' => $this->storeAgendaTypes($cityIds, $countryIds),
        ];
    }

    /**
     * Rewrites the category counts of every city and country, or of these ones only, in one transaction: the pages
     * read the previous counts until it commits. A few thousand rows that only the pages read, unlike the place and
     * city rows the parser worker updates all day, so they are not diffed.
     *
     * @param list<int|string>|null $cityIds    the cities to recount, null for all of them
     * @param list<int|string>|null $countryIds the countries to recount, null for all of them
     *
     * @return int the number of rows stored
     */
    private function storeCategories(?array $cityIds = null, ?array $countryIds = null): int
    {
        if ([] === $cityIds && [] === $countryIds) {
            return 0;
        }

        [$cities, $countries] = self::addUpByZone(
            $this->eventRepository->countUpcomingCategoriesByZone($cityIds, $countryIds),
            static fn (array $row): array => [$row['category']],
            $cityIds,
            $countryIds,
        );

        // tag_id, events, city_id, country_id
        $rows = [];
        foreach ($cities as $city => $categories) {
            foreach ($categories as $category => $events) {
                $rows[] = [$category, $events, $city, null];
            }
        }

        foreach ($countries as $country => $categories) {
            foreach ($categories as $category => $events) {
                $rows[] = [$category, $events, null, $country];
            }
        }

        $this->entityManager->getConnection()->transactional(static function (Connection $connection) use ($rows, $cityIds, $countryIds): void {
            if (null === $cityIds && null === $countryIds) {
                $connection->executeStatement('DELETE FROM upcoming_category');
            } else {
                $connection->executeStatement(
                    'DELETE FROM upcoming_category WHERE city_id IN (?) OR country_id IN (?)',
                    [$cityIds ?? [], $countryIds ?? []],
                    [ArrayParameterType::INTEGER, ArrayParameterType::STRING],
                );
            }

            foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
                $connection->executeStatement(
                    'INSERT INTO upcoming_category (tag_id, events, city_id, country_id) VALUES ' . implode(', ', array_fill(0, \count($chunk), '(?, ?, ?, ?)')),
                    array_merge(...$chunk),
                );
            }
        });

        return \count($rows);
    }

    /**
     * Adds up the agenda types of the events to come by city and by country, and writes the ones whose counts
     * changed: of every city and country, or of these ones only.
     *
     * @param list<int|string>|null $cityIds    the cities to recount, null for all of them
     * @param list<int|string>|null $countryIds the countries to recount, null for all of them
     *
     * @return int the number of cities and countries written
     */
    private function storeAgendaTypes(?array $cityIds = null, ?array $countryIds = null): int
    {
        if ([] === $cityIds && [] === $countryIds) {
            return 0;
        }

        // A row counts the events of a combination of types ("concert,family"): each of them counts them
        [$cities, $countries] = self::addUpByZone(
            $this->eventRepository->countUpcomingAgendaTypesByZone($cityIds, $countryIds),
            static fn (array $row): array => explode(',', (string) $row['types']),
            $cityIds,
            $countryIds,
        );

        return $this->storeTypes(City::class, $cities, $cityIds) + $this->storeTypes(Country::class, $countries, $countryIds);
    }

    /**
     * @param class-string<City|Country>             $class
     * @param array<int|string, array<string, int>> $counts the type counts just computed, by id (the zones without any left out)
     * @param list<int|string>|null                  $ids    the rows these counts cover, null for all of them
     *
     * @return int the number of rows written
     */
    private function storeTypes(string $class, array $counts, ?array $ids): int
    {
        if ([] === $ids) {
            return 0;
        }

        $qb = $this
            ->entityManager
            ->createQueryBuilder()
            ->select('x.id', 'x.upcomingAgendaTypes')
            ->from($class, 'x')
            ->where('x.upcomingAgendaTypes IS NOT NULL');

        if (null !== $ids) {
            $qb
                ->andWhere('x.id IN (:ids)')
                ->setParameter('ids', $ids);
        }

        // A scalar result leaves the column as stored, and MySQL stores the keys of a JSON object in its own order
        $stored = [];
        foreach ($qb->getQuery()->getScalarResult() as $row) {
            $stored[$row['id']] = self::sortedTypes((array) json_decode((string) $row['upcomingAgendaTypes'], true, flags: \JSON_THROW_ON_ERROR));
        }

        $changes = [];
        foreach ($counts as $id => $types) {
            $types = self::sortedTypes($types);
            if (($stored[$id] ?? []) !== $types) {
                $changes[json_encode($types, \JSON_THROW_ON_ERROR)][] = $id;
            }
        }

        // Had events of a type at the last count, has none left
        foreach (array_keys(array_diff_key($stored, $counts)) as $id) {
            $changes[''][] = $id;
        }

        $written = 0;
        foreach ($changes as $types => $changed) {
            foreach (array_chunk($changed, self::BATCH_SIZE) as $chunk) {
                $written += (int) $this
                    ->entityManager
                    ->createQueryBuilder()
                    ->update($class, 'x')
                    ->set('x.upcomingAgendaTypes', ':types')
                    ->where('x.id IN (:ids)')
                    ->setParameter('types', '' === $types ? null : json_decode($types, true, flags: \JSON_THROW_ON_ERROR), Types::JSON)
                    ->setParameter('ids', $chunk)
                    ->getQuery()
                    ->execute();
            }
        }

        return $written;
    }

    /**
     * @param array<array-key, mixed> $types counts by AgendaType value
     *
     * @return array<string, int> the counts in AgendaType order, those of the types that no longer exist left out
     */
    private static function sortedTypes(array $types): array
    {
        $sorted = [];
        foreach (AgendaType::cases() as $type) {
            if (isset($types[$type->value])) {
                $sorted[$type->value] = (int) $types[$type->value];
            }
        }

        return $sorted;
    }

    /**
     * Adds up the counts of the rows by the city and by the country of their venue, for the recounted ones only: a
     * venue of a recounted country may be in a city that is not recounted, and the other way around. Returns the counts
     * by key of the cities, then of the countries.
     *
     * @param iterable<array{city: int|string|null, country: string|null, events: int|string}> $rows
     * @param callable(array<string, mixed>): list<int|string>                                 $keys       what each row counts the events of
     * @param list<int|string>|null                                                            $cityIds    the cities to keep, null for all of them
     * @param list<int|string>|null                                                            $countryIds the countries to keep, null for all of them
     *
     * @return array{array<int|string, array<int|string, int>>, array<int|string, array<int|string, int>>}
     */
    private static function addUpByZone(iterable $rows, callable $keys, ?array $cityIds, ?array $countryIds): array
    {
        $keptCities = null === $cityIds ? null : array_flip($cityIds);
        $keptCountries = null === $countryIds ? null : array_flip($countryIds);
        $cities = [];
        $countries = [];
        foreach ($rows as $row) {
            $events = (int) $row['events'];
            foreach ($keys($row) as $key) {
                if (null !== $row['city'] && (null === $keptCities || isset($keptCities[$row['city']]))) {
                    $cities[$row['city']][$key] = ($cities[$row['city']][$key] ?? 0) + $events;
                }

                if (null !== $row['country'] && (null === $keptCountries || isset($keptCountries[$row['country']]))) {
                    $countries[$row['country']][$key] = ($countries[$row['country']][$key] ?? 0) + $events;
                }
            }
        }

        return [$cities, $countries];
    }

    /**
     * @param 'city'|'country'      $association
     * @param list<int|string>|null $ids         the cities or countries to add up, null for all of them
     *
     * @return array<int|string, int> the stored counts of the venues, added up by city or country id
     */
    private function sumPlacesBy(string $association, ?array $ids = null): array
    {
        $qb = $this
            ->entityManager
            ->createQueryBuilder()
            ->select(\sprintf('IDENTITY(p.%s) AS id', $association), 'SUM(p.upcomingEvents) AS events')
            ->from(Place::class, 'p')
            ->where('p.upcomingEvents > 0')
            ->andWhere(\sprintf('p.%s IS NOT NULL', $association))
            ->groupBy(\sprintf('p.%s', $association));

        if (null !== $ids) {
            $qb
                ->andWhere(\sprintf('p.%s IN (:ids)', $association))
                ->setParameter('ids', $ids);
        }

        $rows = $qb->getQuery()->getScalarResult();

        return array_map(intval(...), array_column($rows, 'events', 'id'));
    }

    /**
     * @param class-string<Country|City|Place> $class
     * @param array<int|string, int>           $counts the counts just computed, by id (the ones without events left out)
     * @param list<int|string>|null            $ids    the rows these counts cover, null for all of them
     *
     * @return int the number of rows written
     */
    private function store(string $class, array $counts, ?array $ids = null): int
    {
        $qb = $this
            ->entityManager
            ->createQueryBuilder()
            ->select('x.id', 'x.upcomingEvents')
            ->from($class, 'x')
            ->where('x.upcomingEvents > 0');

        if (null !== $ids) {
            $qb
                ->andWhere('x.id IN (:ids)')
                ->setParameter('ids', $ids);
        }

        $rows = $qb->getQuery()->getScalarResult();
        $stored = array_map(intval(...), array_column($rows, 'upcomingEvents', 'id'));

        $written = 0;
        foreach (self::changes($stored, $counts) as $events => $ids) {
            foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
                $written += (int) $this
                    ->entityManager
                    ->createQueryBuilder()
                    ->update($class, 'x')
                    ->set('x.upcomingEvents', ':events')
                    ->where('x.id IN (:ids)')
                    ->setParameter('events', $events)
                    ->setParameter('ids', $chunk)
                    ->getQuery()
                    ->execute();
            }
        }

        return $written;
    }

    /**
     * @param list<int|string|null> $ids
     *
     * @return list<int|string> the ids given, without nulls and duplicates
     */
    private static function ids(array $ids): array
    {
        return array_values(array_unique(array_filter($ids, static fn (int|string|null $id): bool => null !== $id)));
    }

    /**
     * The rows to write, grouped by their new count so that one UPDATE sets a whole group.
     *
     * @param array<int|string, int> $stored the counts in the database now, by id (the zeros left out)
     * @param array<int|string, int> $fresh  the counts just computed, by id (the zeros left out)
     *
     * @return array<int, list<int|string>> the ids to write, by their new count
     */
    private static function changes(array $stored, array $fresh): array
    {
        $changes = [];
        foreach ($fresh as $id => $events) {
            if (($stored[$id] ?? 0) !== $events) {
                $changes[$events][] = $id;
            }
        }

        // Had events at the last count, has none left
        foreach (array_keys(array_diff_key($stored, $fresh)) as $id) {
            $changes[0][] = $id;
        }

        return $changes;
    }
}
