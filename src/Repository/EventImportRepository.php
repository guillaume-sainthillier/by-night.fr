<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\EventImport;
use App\Entity\Publisher;
use App\Entity\User;
use App\Enum\EventImportStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventImport>
 *
 * @method EventImport|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventImport|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventImport[]    findAll()
 * @method EventImport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class EventImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventImport::class);
    }

    /**
     * @return EventImport[] the publisher's latest, newest first
     */
    public function findLatestByPublisher(Publisher $publisher, int $limit = 5): array
    {
        return $this->findBy(['publisher' => $publisher], ['id' => 'DESC'], $limit);
    }

    public function countRunningByUser(User $user): int
    {
        return $this->count(['user' => $user, 'status' => [EventImportStatus::Uploaded, EventImportStatus::Checking, EventImportStatus::Importing]]);
    }

    public function countRunningByPublisher(Publisher $publisher): int
    {
        return $this->count(['publisher' => $publisher, 'status' => [EventImportStatus::Uploaded, EventImportStatus::Checking, EventImportStatus::Importing]]);
    }

    /**
     * Moves it from one of the given statuses to another, in one conditional UPDATE: of two requests (a double
     * click, two tabs), only one moves it. The entity is left as is: refresh it when it matters.
     *
     * @param list<EventImportStatus> $from
     *
     * @return bool whether it moved
     */
    public function transition(EventImport $entity, array $from, EventImportStatus $to): bool
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
            ->setParameter('from', array_map(static fn (EventImportStatus $status): string => $status->value, $from))
            ->getQuery()
            ->execute();
    }

    /**
     * Its status as saved now, whatever the entity says: a member may have cancelled it meanwhile.
     */
    public function findStatus(EventImport $import): ?EventImportStatus
    {
        $status = $this->getEntityManager()->getConnection()->fetchOne('SELECT status FROM event_import WHERE id = ?', [$import->getId()]);

        return \is_string($status) ? EventImportStatus::from($status) : null;
    }

    /**
     * @return EventImport[] the ones a worker should be on but has not touched since then: its worker died
     */
    public function findStuckSince(DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status IN (:running)')
            ->andWhere('t.updatedAt < :since')
            ->setParameter('running', array_map(static fn (EventImportStatus $status): string => $status->value, [EventImportStatus::Uploaded, EventImportStatus::Checking, EventImportStatus::Importing]))
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EventImport[] the ones created before then, done with (no worker on them)
     */
    public function findExpiredBefore(DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status NOT IN (:running)')
            ->andWhere('t.createdAt < :before')
            ->setParameter('running', array_map(static fn (EventImportStatus $status): string => $status->value, [EventImportStatus::Uploaded, EventImportStatus::Checking, EventImportStatus::Importing]))
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }
}
