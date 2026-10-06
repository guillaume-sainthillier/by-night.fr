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
     * The links touching any of these events, those kept apart included.
     *
     * @param int[] $eventIds
     *
     * @return list<array{id: int, eventId: int, linkedEventId: int, keptApart: bool}>
     */
    public function findTouching(array $eventIds): array
    {
        if ([] === $eventIds) {
            return [];
        }

        /** @var list<array{id: int|string, eventId: int|string, linkedEventId: int|string, keptApart: bool|int|string}> $rows */
        $rows = $this
            ->createQueryBuilder('l')
            ->select('l.id, IDENTITY(l.event) AS eventId, IDENTITY(l.linkedEvent) AS linkedEventId, l.keptApart')
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
        ], $rows);
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
