<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Contracts\MultipleEagerLoaderInterface;
use App\Entity\ContentRemovalRequest;
use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentRemovalRequest>
 *
 * @implements MultipleEagerLoaderInterface<ContentRemovalRequest>
 *
 * @method ContentRemovalRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method ContentRemovalRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method ContentRemovalRequest[]    findAll()
 * @method ContentRemovalRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class ContentRemovalRequestRepository extends ServiceEntityRepository implements MultipleEagerLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentRemovalRequest::class);
    }

    public function loadAllEager(array $entities, array $context = []): void
    {
        if ('admin:index' !== ($context['view'] ?? null)) {
            return;
        }

        // The index counts the events of each request: one query fills every collection of the page
        $this
            ->createQueryBuilder('cr')
            ->select('PARTIAL cr.{id}')
            ->addSelect('e')
            ->leftJoin('cr.events', 'e')
            ->where('cr.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (ContentRemovalRequest $entity) => $entity->getId(), $entities))
            ->getQuery()
            ->execute();
    }

    /**
     * @return ContentRemovalRequest[]
     */
    public function findByEvent(Event $event): array
    {
        return $this->createQueryBuilder('cr')
            ->innerJoin('cr.events', 'e')
            ->where('e = :event')
            ->setParameter('event', $event)
            ->getQuery()
            ->getResult();
    }
}
