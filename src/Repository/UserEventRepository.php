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
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

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
     * @return list<UserEvent> a member's calendar, with its events loaded
     */
    public function findByUserWithEvents(User $user): array
    {
        /* @var list<UserEvent> */
        return $this
            ->createQueryBuilder('ue')
            ->addSelect('e')
            ->join('ue.event', 'e')
            ->where('ue.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * A member's outings ending on or after a day, as their calendar feed lists them: the published events they go to
     * or are interested in (a "J'y vais" taken back keeps its row, neither), with their sessions, soonest first.
     *
     * @return list<UserEvent>
     */
    public function findCalendarFeed(User $user, DateTimeInterface $since): array
    {
        /* @var list<UserEvent> */
        return $this
            ->createQueryBuilder('ue')
            ->addSelect('e', 't')
            ->join('ue.event', 'e')
            ->leftJoin('e.timesheets', 't')
            ->where('ue.user = :user')
            ->andWhere('(ue.going = true OR ue.wish = true)')
            ->andWhere('e.draft = false')
            ->andWhere('e.endDate >= :since')
            ->orderBy('e.startDate', SortDirection::Ascending)
            ->addOrderBy('e.id', SortDirection::Ascending)
            ->setParameter('user', $user->getId())
            ->setParameter('since', $since->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    /**
     * The members following these events, with their event, its venue and the member loaded: those going to them or
     * interested in them, their author and the member managing their publisher left out (publishing an event says "J'y
     * vais" for them, and they make its changes: Publisher::isManagedBy()), and none of a duplicate (its page leads to
     * its canonical's).
     *
     * @param list<int> $eventIds
     *
     * @return list<UserEvent>
     */
    public function findFollowersOfEvents(array $eventIds): array
    {
        if ([] === $eventIds) {
            return [];
        }

        /* @var list<UserEvent> */
        return $this
            ->createQueryBuilder('ue')
            ->addSelect('e', 'p', 'u')
            ->join('ue.event', 'e')
            ->join('ue.user', 'u')
            ->leftJoin('e.place', 'p')
            ->leftJoin('e.publisher', 'pub')
            ->where('ue.event IN (:ids)')
            ->andWhere('(ue.going = true OR ue.wish = true)')
            ->andWhere('e.duplicateOf IS NULL')
            ->andWhere('(e.user IS NULL OR e.user <> ue.user)')
            ->andWhere('(pub.id IS NULL OR pub.owner <> ue.user)')
            ->setParameter('ids', $eventIds)
            ->getQuery()
            ->getResult();
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
