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
use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Manager\PreloadManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserEvent>
 *
 * @implements MultipleEagerLoaderInterface<UserEvent>
 *
 * @method UserEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserEvent[]    findAll()
 * @method UserEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class UserEventRepository extends ServiceEntityRepository implements MultipleEagerLoaderInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly PreloadManager $preloadManager,
    ) {
        parent::__construct($registry, UserEvent::class);
    }

    /**
     * The participations (going) and the interests (wish) of each event, which its counters store
     * (EventParticipationManager::recount()).
     *
     * @param list<int> $eventIds
     *
     * @return array<int, array{participations: int, interests: int}> by event id; an event nobody follows is left out
     */
    public function countByEvents(array $eventIds): array
    {
        $counts = [];
        foreach (array_chunk($eventIds, 1_000) as $chunk) {
            /** @var list<array{event: int|string, participations: int|string|null, interests: int|string|null}> $rows */
            $rows = $this
                ->createQueryBuilder('ue')
                ->select('IDENTITY(ue.event) AS event')
                ->addSelect('SUM(CASE WHEN ue.going = true THEN 1 ELSE 0 END) AS participations')
                ->addSelect('SUM(CASE WHEN ue.wish = true THEN 1 ELSE 0 END) AS interests')
                ->join('ue.user', 'u')
                ->where('ue.event IN (:ids)')
                ->groupBy('ue.event')
                ->setParameter('ids', $chunk)
                ->getQuery()
                ->getArrayResult();

            foreach ($rows as $row) {
                $counts[(int) $row['event']] = ['participations' => (int) $row['participations'], 'interests' => (int) $row['interests']];
            }
        }

        return $counts;
    }

    public function loadAllEager(array $entities, array $context = []): void
    {
        if ('admin:index' === ($context['view'] ?? null)) {
            $this->preloadManager->preloadEntities(Event::class, array_map(static fn (UserEvent $entity) => $entity->getEvent()?->getId(), $entities));
            $this->preloadManager->preloadEntities(User::class, array_map(static fn (UserEvent $entity) => $entity->getUser()?->getId(), $entities));
        }
    }
}
