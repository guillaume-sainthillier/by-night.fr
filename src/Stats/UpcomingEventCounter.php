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
use App\Repository\EventRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stores the number of published events to come on every place, city and country, and of each category in every
 * city and country (UpcomingCategory), so the portals rank and count them with an indexed read instead of grouping
 * every event to come on each page.
 *
 * Run daily just after midnight by app:events:count-upcoming, when yesterday's events stop being "to come"; the
 * events imported during the day show in the counts the next night. The rows are written by DQL bulk updates: no
 * lifecycle callback runs, so no updatedAt changes and nothing is reindexed.
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
     * @return array{countries: int, cities: int, places: int, categories: int} the number of rows whose count changed,
     *                                                                          and of category counts stored
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
        ];
    }

    /**
     * Rewrites the category counts of every city and country in one transaction: the pages read the previous counts
     * until it commits. A few thousand rows that only the pages read, unlike the place and city rows the parser
     * worker updates all day, so they are not diffed.
     *
     * @return int the number of rows stored
     */
    private function storeCategories(): int
    {
        $cities = [];
        $countries = [];
        foreach ($this->eventRepository->countUpcomingCategoriesByZone() as $row) {
            $events = (int) $row['events'];
            if (null !== $row['city']) {
                $cities[$row['city']][$row['category']] = ($cities[$row['city']][$row['category']] ?? 0) + $events;
            }

            if (null !== $row['country']) {
                $countries[$row['country']][$row['category']] = ($countries[$row['country']][$row['category']] ?? 0) + $events;
            }
        }

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

        $this->entityManager->getConnection()->transactional(static function (Connection $connection) use ($rows): void {
            $connection->executeStatement('DELETE FROM upcoming_category');
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
     * @param 'city'|'country' $association
     *
     * @return array<int|string, int> the stored counts of the venues, added up by city or country id
     */
    private function sumPlacesBy(string $association): array
    {
        $rows = $this
            ->entityManager
            ->createQueryBuilder()
            ->select(\sprintf('IDENTITY(p.%s) AS id', $association), 'SUM(p.upcomingEvents) AS events')
            ->from(Place::class, 'p')
            ->where('p.upcomingEvents > 0')
            ->andWhere(\sprintf('p.%s IS NOT NULL', $association))
            ->groupBy(\sprintf('p.%s', $association))
            ->getQuery()
            ->getScalarResult();

        return array_map(intval(...), array_column($rows, 'events', 'id'));
    }

    /**
     * @param class-string<Country|City|Place> $class
     * @param array<int|string, int>           $counts the counts just computed, by id (the ones without events left out)
     *
     * @return int the number of rows written
     */
    private function store(string $class, array $counts): int
    {
        $rows = $this
            ->entityManager
            ->createQueryBuilder()
            ->select('x.id', 'x.upcomingEvents')
            ->from($class, 'x')
            ->where('x.upcomingEvents > 0')
            ->getQuery()
            ->getScalarResult();
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
