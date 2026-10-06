<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\CrossSourceLink;
use App\Entity\Event;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CrossSourceLink>
 */
final class CrossSourceLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrossSourceLink::class);
    }

    /**
     * The links touching any of these events, those kept apart included, with the venue of both events.
     *
     * @param int[] $eventIds
     *
     * @return list<array{id: int, eventId: int, linkedEventId: int, keptApart: bool, eventPlaceId: int|null, linkedEventPlaceId: int|null}>
     */
    public function findTouching(array $eventIds): array
    {
        if ([] === $eventIds) {
            return [];
        }

        /** @var list<array{id: int|string, eventId: int|string, linkedEventId: int|string, keptApart: bool|int|string, eventPlaceId: int|string|null, linkedEventPlaceId: int|string|null}> $rows */
        $rows = $this
            ->createQueryBuilder('l')
            ->select('l.id, IDENTITY(l.event) AS eventId, IDENTITY(l.linkedEvent) AS linkedEventId, l.keptApart')
            ->addSelect('IDENTITY(e.place) AS eventPlaceId, IDENTITY(le.place) AS linkedEventPlaceId')
            ->join('l.event', 'e')
            ->join('l.linkedEvent', 'le')
            ->where('l.event IN (:ids)')
            ->orWhere('l.linkedEvent IN (:ids)')
            ->setParameter('ids', array_values(array_unique($eventIds)))
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'eventId' => (int) $row['eventId'],
            'linkedEventId' => (int) $row['linkedEventId'],
            'keptApart' => (bool) $row['keptApart'],
            'eventPlaceId' => null !== $row['eventPlaceId'] ? (int) $row['eventPlaceId'] : null,
            'linkedEventPlaceId' => null !== $row['linkedEventPlaceId'] ? (int) $row['linkedEventPlaceId'] : null,
        ], $rows);
    }

    /**
     * The venues of the linked imported events that are not over, either side of a link not kept apart.
     *
     * @return list<int>
     */
    public function findPlaceIdsOfLinkedEvents(DateTimeImmutable $from): array
    {
        $placeIds = [];
        foreach (['event', 'linkedEvent'] as $side) {
            $ids = $this
                ->createQueryBuilder('l')
                ->select('DISTINCT IDENTITY(e.place) AS placeId')
                ->join('l.' . $side, 'e')
                ->where('l.keptApart = false')
                ->andWhere('e.fromData IS NOT NULL')
                ->andWhere('e.place IS NOT NULL')
                ->andWhere('e.endDate >= :from')
                ->setParameter('from', $from->format('Y-m-d'))
                ->getQuery()
                ->getSingleColumnResult();
            foreach ($ids as $id) {
                $placeIds[(int) $id] = (int) $id;
            }
        }

        return array_values($placeIds);
    }

    /**
     * Keep the event apart from every event it is linked to.
     *
     * @return list<int> the events it was linked to
     */
    public function keepApart(int $eventId): array
    {
        $linked = [];
        foreach ($this->findBy(['event' => $eventId]) as $link) {
            $linked[] = (int) $link->getLinkedEvent()->getId();
            $link->setKeptApart(true);
        }

        foreach ($this->findBy(['linkedEvent' => $eventId]) as $link) {
            $linked[] = (int) $link->getEvent()->getId();
            $link->setKeptApart(true);
        }

        return $linked;
    }

    /**
     * Link two events, the lower id first. Persisted, not flushed.
     */
    public function link(int $leftId, int $rightId): void
    {
        $entityManager = $this->getEntityManager();
        $event = $entityManager->getReference(Event::class, min($leftId, $rightId));
        $linkedEvent = $entityManager->getReference(Event::class, max($leftId, $rightId));
        \assert($event instanceof Event && $linkedEvent instanceof Event);

        $entityManager->persist(new CrossSourceLink($event, $linkedEvent));
    }

    /**
     * @param int[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        if ([] === $ids) {
            return;
        }

        $this
            ->createQueryBuilder('l')
            ->delete()
            ->where('l.id IN (:ids)')
            ->setParameter('ids', array_values($ids))
            ->getQuery()
            ->execute();
    }
}
