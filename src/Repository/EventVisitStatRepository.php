<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\EventVisitStat;
use App\Search\DateRange;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventVisitStat>
 *
 * @method EventVisitStat|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventVisitStat|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventVisitStat[]    findAll()
 * @method EventVisitStat[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class EventVisitStatRepository extends ServiceEntityRepository
{
    private const int INSERT_BATCH_SIZE = 500;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventVisitStat::class);
    }

    /**
     * Replaces the audience of a day with $stats: a day read again (today, every hour) overwrites what was read before.
     * The events that no longer exist are left out.
     *
     * @param array<int, array{visitors: int, ticketClicks: int}> $stats by event id
     *
     * @return int the events written
     */
    public function replaceDay(DateTimeImmutable $day, array $stats): int
    {
        $connection = $this->getEntityManager()->getConnection();
        $day = $day->setTime(0, 0);

        return $connection->transactional(static function () use ($connection, $day, $stats): int {
            $connection->executeStatement('DELETE FROM event_visit_stat WHERE day = :day', ['day' => $day], ['day' => Types::DATE_IMMUTABLE]);

            $written = 0;
            foreach (array_chunk(array_keys($stats), self::INSERT_BATCH_SIZE) as $ids) {
                $existing = $connection->fetchFirstColumn('SELECT id FROM event WHERE id IN (:ids)', ['ids' => $ids], ['ids' => ArrayParameterType::INTEGER]);
                if ([] === $existing) {
                    continue;
                }

                $values = [];
                $params = [];
                $types = [];
                foreach ($existing as $i => $id) {
                    $values[] = "(:event$i, :day, :visitors$i, :clicks$i)";
                    $params["event$i"] = (int) $id;
                    $params["visitors$i"] = $stats[(int) $id]['visitors'];
                    $params["clicks$i"] = $stats[(int) $id]['ticketClicks'];
                }
                $params['day'] = $day;
                $types['day'] = Types::DATE_IMMUTABLE;

                $written += $connection->executeStatement(
                    'INSERT INTO event_visit_stat (event_id, day, visitors, ticket_clicks) VALUES ' . implode(', ', $values),
                    $params,
                    $types,
                );
            }

            return $written;
        });
    }

    /**
     * The visitors and ticketing clicks of each event since $since (that day included).
     *
     * @param int[] $eventIds
     *
     * @return array<int, array{visitors: int, ticketClicks: int}> by event id, the events without any left out
     */
    public function findTotalsByEvents(array $eventIds, DateTimeImmutable $since): array
    {
        if ([] === $eventIds) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT event_id, SUM(visitors) AS visitors, SUM(ticket_clicks) AS clicks FROM event_visit_stat WHERE event_id IN (:ids) AND day >= :since GROUP BY event_id',
            ['ids' => array_values(array_unique($eventIds)), 'since' => $since->setTime(0, 0)],
            ['ids' => ArrayParameterType::INTEGER, 'since' => Types::DATE_IMMUTABLE],
        );

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['event_id']] = ['visitors' => (int) $row['visitors'], 'ticketClicks' => (int) $row['clicks']];
        }

        return $totals;
    }

    /**
     * The audience of an event day by day in $range, the days without any left out.
     *
     * @return array<string, array{visitors: int, ticketClicks: int, fromGoogleAnalytics: bool}> by day (Y-m-d)
     */
    public function findDaily(int $eventId, DateRange $range): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT day, visitors, ticket_clicks, from_google_analytics FROM event_visit_stat WHERE event_id = :id AND day BETWEEN :from AND :to ORDER BY day',
            ['id' => $eventId, 'from' => $range->from, 'to' => $range->to ?? new DateTimeImmutable('today')],
            ['from' => Types::DATE_IMMUTABLE, 'to' => Types::DATE_IMMUTABLE],
        );

        $daily = [];
        foreach ($rows as $row) {
            $daily[substr((string) $row['day'], 0, 10)] = [
                'visitors' => (int) $row['visitors'],
                'ticketClicks' => (int) $row['ticket_clicks'],
                'fromGoogleAnalytics' => (bool) $row['from_google_analytics'],
            ];
        }

        return $daily;
    }
}
