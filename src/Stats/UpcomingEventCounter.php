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
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\UpcomingCount;
use App\Enum\AgendaType;
use App\Repository\EventRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;

/**
 * Stores the number of published events to come on every place, city and country, and of each category and each
 * agenda type in every city and country (UpcomingCount), so the portals rank and count them with an indexed read
 * instead of grouping every event to come on each page.
 *
 * Run daily just after midnight by app:events:count-upcoming, when yesterday's events stop being "to come"; the
 * events imported during the day show in the counts the next night. An event created, changed or deleted on the site
 * (personal space, back office) is recounted right away, for its venues only (refreshPlaces(), see
 * UpcomingEventCountListener), and so are the type counts of its city and country once its types are found, as soon
 * as it is indexed (refreshAgendaTypesOfEvents(), see ClassifyEventsHandler). The type counts of every city and
 * country are recounted again by app:events:classify-agenda-types, run after the parsers (refreshAgendaTypes()). The
 * rows are written by DQL bulk updates: no lifecycle callback runs, so no updatedAt changes and nothing is
 * reindexed.
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
        /** How many cities or countries are counted by category or type at once: what the memory holds */
        private int $zonesPerPage = 500,
    ) {
    }

    /**
     * Returns the number of rows whose count changed, and of category and type counts stored.
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
            // After the cities and countries: their counts pick the zones to count by category and type
            'categories' => $this->storeCounts(Dimension::Category),
            'types' => $this->storeCounts(Dimension::AgendaType),
        ];
    }

    /**
     * Recounts the agenda types of every city and country: app:events:classify-agenda-types, once the parsers ran.
     *
     * @return int the number of type counts stored
     */
    public function refreshAgendaTypes(): int
    {
        return $this->storeCounts(Dimension::AgendaType);
    }

    /**
     * Recounts the agenda types of the cities and countries of these events, once their types changed on the site.
     *
     * @param list<int> $eventIds
     *
     * @return int the number of type counts stored
     */
    public function refreshAgendaTypesOfEvents(array $eventIds): int
    {
        [$cityIds, $countryIds] = $this->zonesOf($this
            ->entityManager
            ->createQueryBuilder()
            ->from(Event::class, 'e')
            ->join('e.place', 'p')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $eventIds));

        return $this->storeCountsOf(Dimension::AgendaType, $cityIds, $countryIds);
    }

    /**
     * Recounts these venues, their cities and countries, and the category and type counts of those cities and
     * countries: what a change to the events of these venues can move. The other rows keep the counts they have.
     * Returns the number of rows whose count changed, and of category and type counts stored.
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

        [$cityIds, $countryIds] = $this->zonesOf($this
            ->entityManager
            ->createQueryBuilder()
            ->from(Place::class, 'p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $placeIds));

        $places = $this->store(Place::class, $this->eventRepository->countUpcomingByPlace($placeIds), $placeIds);

        return [
            'countries' => [] === $countryIds ? 0 : $this->store(Country::class, $this->sumPlacesBy('country', $countryIds), $countryIds),
            'cities' => [] === $cityIds ? 0 : $this->store(City::class, $this->sumPlacesBy('city', $cityIds), $cityIds),
            'places' => $places,
            'categories' => $this->storeCountsOf(Dimension::Category, $cityIds, $countryIds),
            'types' => $this->storeCountsOf(Dimension::AgendaType, $cityIds, $countryIds),
        ];
    }

    /**
     * @param QueryBuilder $qb the venues ("p")
     *
     * @return array{list<int|string>, list<int|string>} the cities, then the countries of these venues
     */
    private function zonesOf(QueryBuilder $qb): array
    {
        $zones = $qb
            ->select('IDENTITY(p.city) AS city', 'IDENTITY(p.country) AS country')
            ->distinct()
            ->getQuery()
            ->getScalarResult();

        return [self::ids(array_column($zones, 'city')), self::ids(array_column($zones, 'country'))];
    }

    /**
     * Rewrites the counts of a dimension of these cities and countries, a page of them at a time.
     *
     * @param list<int|string> $cityIds
     * @param list<int|string> $countryIds
     *
     * @return int the number of rows stored
     */
    private function storeCountsOf(Dimension $dimension, array $cityIds, array $countryIds): int
    {
        $stored = 0;
        foreach (['city' => $cityIds, 'country' => $countryIds] as $zone => $ids) {
            foreach (array_chunk($ids, $this->zonesPerPage) as $page) {
                $stored += $this->storeZoneCounts($dimension, $zone, $page);
            }
        }

        return $stored;
    }

    /**
     * Rewrites the counts of a dimension of every city and country with events to come, a page of zones at a time,
     * then removes those of the zones left without any.
     *
     * @return int the number of rows stored
     */
    private function storeCounts(Dimension $dimension): int
    {
        $stored = 0;
        foreach ([City::class => 'city', Country::class => 'country'] as $class => $zone) {
            foreach ($this->zonePages($class) as $ids) {
                $stored += $this->storeZoneCounts($dimension, $zone, $ids);
            }

            // The zones no page holds: their counts, if any, are of events that are over
            $this
                ->entityManager
                ->createQueryBuilder()
                ->delete(UpcomingCount::class, 'uc')
                ->where(\sprintf('uc.%s IS NOT NULL', $dimension->field()))
                ->andWhere(\sprintf('uc.%1$s IN (SELECT z.id FROM %2$s z WHERE z.upcomingEvents = 0)', $zone, $class))
                ->getQuery()
                ->execute();
        }

        return $stored;
    }

    /**
     * The cities or countries that have events to come, by pages of ids: a cursor on the id, so a page costs what the
     * first one does. Only their ids are read: no entity to clear.
     *
     * @param class-string<City|Country> $class
     *
     * @return iterable<list<int|string>>
     */
    private function zonePages(string $class): iterable
    {
        $pagination = new CursorPagination(
            $this->entityManager->createQueryBuilder()->select('z.id')->from($class, 'z')->where('z.upcomingEvents > 0'),
            new OrderConfigurations(new OrderConfiguration('z.id', static fn (array $zone): int|string => $zone['id'])),
            $this->zonesPerPage,
            fetchJoinCollection: false,
        );

        foreach ($pagination->getChunkResults() as $zones) {
            yield array_column($zones, 'id');
        }
    }

    /**
     * Rewrites the counts of a dimension of these cities or countries in one transaction: the pages read the previous
     * counts until it commits. A few rows by zone that only the pages read, unlike the place and city rows the parser
     * worker updates all day, so they are not diffed.
     *
     * @param 'city'|'country' $zone
     * @param list<int|string> $ids
     *
     * @return int the number of rows stored
     */
    private function storeZoneCounts(Dimension $dimension, string $zone, array $ids): int
    {
        // zone id => value => events
        $counts = [];
        if (Dimension::Category === $dimension) {
            foreach ($this->eventRepository->countUpcomingCategoriesIn($zone, $ids) as $row) {
                $counts[$row['zone']][$row['category']] = (int) $row['events'];
            }
        } else {
            // A row counts the events of a combination of types ("concert,family"): each of them counts them
            foreach ($this->eventRepository->countUpcomingAgendaTypesIn($zone, $ids) as $row) {
                foreach (explode(',', $row['types']) as $type) {
                    if (null !== AgendaType::tryFrom($type)) {
                        $counts[$row['zone']][$type] = ($counts[$row['zone']][$type] ?? 0) + (int) $row['events'];
                    }
                }
            }
        }

        // value, events, zone id
        $rows = [];
        foreach ($counts as $id => $values) {
            foreach ($values as $value => $events) {
                $rows[] = [$value, $events, $id];
            }
        }

        $column = $dimension->column();
        $zoneColumn = $zone . '_id';
        $this->entityManager->getConnection()->transactional(static function (Connection $connection) use ($rows, $ids, $column, $zoneColumn, $zone): void {
            $connection->executeStatement(
                \sprintf('DELETE FROM upcoming_count WHERE %s IS NOT NULL AND %s IN (?)', $column, $zoneColumn),
                [$ids],
                ['city' === $zone ? ArrayParameterType::INTEGER : ArrayParameterType::STRING],
            );

            foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
                $connection->executeStatement(
                    \sprintf('INSERT INTO upcoming_count (%s, events, %s) VALUES ', $column, $zoneColumn) . implode(', ', array_fill(0, \count($chunk), '(?, ?, ?)')),
                    array_merge(...$chunk),
                );
            }
        });

        return \count($rows);
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
