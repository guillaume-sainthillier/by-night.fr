<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\EventExport;
use App\Entity\User;
use App\Enum\EventExportStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventExport>
 *
 * @method EventExport|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventExport|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventExport[]    findAll()
 * @method EventExport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class EventExportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventExport::class);
    }

    /**
     * @return EventExport[] the member's latest, newest first
     */
    public function findLatestByUser(User $user, int $limit = 5): array
    {
        return $this->findBy(['user' => $user], ['id' => 'DESC'], $limit);
    }

    public function countRunningByUser(User $user): int
    {
        return $this->count(['user' => $user, 'status' => [EventExportStatus::Pending, EventExportStatus::InProgress]]);
    }

    /**
     * Moves it from one of the given statuses to another, in one conditional UPDATE: of two requests (a double
     * click, two tabs), only one moves it. The entity is left as is: refresh it when it matters.
     *
     * @param list<EventExportStatus> $from
     *
     * @return bool whether it moved
     */
    public function transition(EventExport $entity, array $from, EventExportStatus $to): bool
    {
        return 1 === $this->createQueryBuilder('t')
            ->update()
            ->set('t.status', ':to')
            ->set('t.updatedAt', ':now')
            ->where('t.id = :id')
            ->andWhere('t.status IN (:from)')
            ->setParameter('to', $to->value)
            ->setParameter('now', new DateTimeImmutable())
            ->setParameter('id', $entity->getId())
            ->setParameter('from', array_map(static fn (EventExportStatus $status): string => $status->value, $from))
            ->getQuery()
            ->execute();
    }

    /**
     * @return EventExport[] the ones a worker should be on but has not touched since then: its worker died
     */
    public function findStuckSince(DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status IN (:running)')
            ->andWhere('t.updatedAt < :since')
            ->setParameter('running', array_map(static fn (EventExportStatus $status): string => $status->value, [EventExportStatus::Pending, EventExportStatus::InProgress]))
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EventExport[] the ones created before then, done with (no worker on them)
     */
    public function findExpiredBefore(DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status NOT IN (:running)')
            ->andWhere('t.createdAt < :before')
            ->setParameter('running', array_map(static fn (EventExportStatus $status): string => $status->value, [EventExportStatus::Pending, EventExportStatus::InProgress]))
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }
}
