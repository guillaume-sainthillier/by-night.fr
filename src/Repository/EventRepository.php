<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\App\Location;
use App\Contracts\DtoFindableRepositoryInterface;
use App\Dto\EventDto;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserEvent;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 *
 * @method Event|null find($id, $lockMode = null, $lockVersion = null)
 * @method Event|null findOneBy(array $criteria, array $orderBy = null)
 * @method Event[]    findAll()
 * @method Event[]    findBy(array $criteria, array $orderBy = null, int $limit = null, $offset = null)
 */
final class EventRepository extends ServiceEntityRepository implements DtoFindableRepositoryInterface
{
    use DtoFindableTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * {@inheritDoc}
     *
     * @return Event[]
     */
    public function findAllByDtos(array $dtos): array
    {
        $qb = parent::createQueryBuilder('e');

        $this->addDtosToQueryBuilder($qb, 'e', $dtos);

        $entityIdsWheres = [];
        foreach ($dtos as $dto) {
            \assert($dto instanceof EventDto);

            if (null === $dto->entityId) {
                continue;
            }

            $entityIdsWheres[$dto->entityId] = true;
        }

        if ([] !== $entityIdsWheres) {
            $qb
                ->orWhere('e.id IN (:ids)')
                ->setParameter('ids', array_keys($entityIdsWheres));
        }

        if (0 === \count($qb->getParameters())) {
            return [];
        }

        return $qb
            ->getQuery()
            ->execute();
    }

    /**
     * User in types.event.persistence.provider.query_builder_method (fos_elastice.yaml)
     */
    public function createIsActiveQueryBuilder(): QueryBuilder
    {
        $from = new DateTime();
        $from->modify(Event::INDEX_FROM);

        $qb = $this->createElasticaQueryBuilder('e');

        return $qb->where('e.draft = false');
    }

    public function createElasticaQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        return $this
            ->createQueryBuilder($alias, $indexBy)
            ->addSelect('c3')
            ->join('p.country', 'c3');
    }

    public function createQueryBuilder($alias, $indexBy = null): QueryBuilder
    {
        $qb = parent::createQueryBuilder($alias, $indexBy);

        return $qb
            ->select($alias, 'p')
            ->addSelect('c')
            ->addSelect('c2')
            ->join($alias . '.place', 'p')
            ->leftJoin('p.city', 'c')
            ->leftJoin('c.parent', 'c2');
    }

    public function createSimpleQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        return parent::createQueryBuilder($alias, $indexBy);
    }

    /**
     * @return iterable<array>
     */
    public function findAllSiteMap(): iterable
    {
        return $this
            ->createQueryBuilder('e')
            ->addSelect('c3')
            ->join('p.country', 'c3')
            ->select('e.slug, e.id, e.updatedAt, e.endDate, c.slug AS city_slug, c3.slug AS country_slug')
            ->getQuery()
            ->toIterable();
    }

    public function updateNonIndexables(): void
    {
        $from = new DateTime();
        $from->modify(Event::INDEX_FROM);

        $this
            ->getEntityManager()
            ->createQuery('UPDATE App:Event e
            SET e.archive = true
            WHERE e.endDate < :from
            AND e.archive = false')
            ->setParameter('from', $from->format('Y-m-d'))
            ->execute();
    }

    public function findNonIndexablesBuilder(): QueryBuilder
    {
        $from = new DateTime();

        $from->modify(Event::INDEX_FROM);

        return $this
            ->createElasticaQueryBuilder('e')
            ->where('e.archive = false')
            ->andWhere('e.endDate < :from')
            ->setParameter('from', $from->format('Y-m-d'))
            ->addOrderBy('e.id');
    }

    public function findAllByUserQueryBuilder(User $user): QueryBuilder
    {
        return $this
            ->createQueryBuilder('e')
            ->where('e.user = :user')
            ->setParameter('user', $user->getId())
            ->orderBy('e.id', Criteria::DESC);
    }

    public function getCountryEvents(): array
    {
        $from = new DateTime();

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c.displayName, c.atDisplayName, c.slug, COUNT(e.id) AS events')
            ->from($this->getEntityName(), 'e')
            ->join('e.place', 'p')
            ->join('p.country', 'c')
            ->where('e.endDate >= :from')
            ->setParameter('from', $from->format('Y-m-d'))
            ->orderBy('events', Criteria::DESC)
            ->groupBy('c.id')
            ->getQuery()
            ->getScalarResult();
    }

    /**
     * @return int[]
     *
     * @psalm-return array<int>
     */
    public function getStatsUser(User $user, string $groupByFunction): array
    {
        $datas = $this->getEntityManager()
            ->createQueryBuilder()
            ->select(\sprintf('%s(e.endDate) as group', $groupByFunction))
            ->addSelect('count(e.id) as events')
            ->from($this->getEntityName(), 'e')
            ->join('e.userEvents', 'ue')
            ->join('ue.user', 'u')
            ->where('u.id = :user')
            ->setParameter('user', $user->getId())
            ->groupBy('group')
            ->getQuery()
            ->getScalarResult();

        $ordered = [];
        foreach ($datas as $data) {
            $ordered[$data['group']] = (int) $data['events'];
        }

        return $ordered;
    }

    public function findAllUserPlaces(User $user, int $limit = 5): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(e) as eventsCount, p.name')
            ->from(UserEvent::class, 'ue')
            ->leftJoin('ue.user', 'u')
            ->leftJoin('ue.event', 'e')
            ->join('e.place', 'p')
            ->where('ue.user = :user')
            ->groupBy('p.name')
            ->orderBy('eventsCount', Criteria::DESC)
            ->setParameter('user', $user->getId())
            ->setFirstResult(0)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAllNextEvents(User $user, bool $isNext = true, int $page = 1, int $limit = 3): array
    {
        return $this
            ->createQueryBuilder('e')
            ->join('e.userEvents', 'cal')
            ->where('cal.user = :user')
            ->andWhere('e.endDate ' . ($isNext ? '>=' : '<') . ' :start_date')
            ->orderBy('e.endDate', $isNext ? 'ASC' : 'DESC')
            ->setParameter('user', $user->getId())
            ->setParameter('start_date', date('Y-m-d'))
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function getUserFavoriteEventsCount(User $user): int
    {
        return (int) $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(u)')
            ->from(UserEvent::class, 'ue')
            ->leftJoin('ue.user', 'u')
            ->where('ue.user = :user')
            ->setParameter('user', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getParticipationTrendsCount(Event $event): int
    {
        return $this->getTrendsCount($event);
    }

    public function getInteretTrendsCount(Event $event): int
    {
        return $this->getTrendsCount($event, false);
    }

    protected function getTrendsCount(Event $event, bool $isParticipation = true): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(u)')
            ->from(UserEvent::class, 'ue')
            ->leftJoin('ue.user', 'u')
            ->where('ue.event = :event')
            ->andWhere(($isParticipation ? 'ue.going' : 'ue.wish') . ' = true')
            ->setParameter('event', $event->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllTrends(Event $event, int $page = 1, int $limit = 7): array
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('u')
            ->addSelect('ue')
            ->addSelect('COUNT(u.id) AS nb_events')
            ->from(User::class, 'u')
            ->join('u.userEvents', 'ue')
            ->where('ue.event = :event')
            ->orderBy('nb_events', Criteria::DESC)
            ->groupBy('u.id')
            ->setParameter('event', $event->getId())
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    /**
     * @return array<Event>
     */
    public function findAllSimilars(Event $event, ?int $page = 1, int $limit = 7): array
    {
        return $this
            ->getFindAllSimilarsBuilder($event)
            ->orderBy('e.name', Criteria::ASC)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    private function getFindAllSimilarsBuilder(Event $event): QueryBuilder
    {
        $qb = $this
            ->createQueryBuilder('e')
            ->where('e.startDate = :from')
            ->andWhere('e.id != :id')
            ->setParameter('from', $event->getStartDate()->format('Y-m-d'))
            ->setParameter('id', $event->getId());

        if (null !== $event->getPlace()->getCity()) {
            $qb
                ->andWhere('p.city = :city')
                ->setParameter('city', $event->getPlace()->getCity()->getId());
        } elseif (null !== $event->getPlace()->getCountry()) {
            $qb
                ->andWhere('p.country = :country')
                ->setParameter('country', $event->getPlace()->getCountry()->getId());
        }

        return $qb;
    }

    public function getAllSimilarsCount(Event $event): int
    {
        return (int) $this
            ->getFindAllSimilarsBuilder($event)
            ->select('count(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllNext(Event $event, int $page = 1, int $limit = 7): array
    {
        $from = new DateTime();

        return $this
            ->createQueryBuilder('e')
            ->where('e.endDate >= :end_date AND e.id != :id AND e.place = :place')
            ->orderBy('e.endDate', Criteria::ASC)
            ->setParameter('end_date', $from->format('Y-m-d'))
            ->setParameter('id', $event->getId())
            ->setParameter('place', $event->getPlace()->getId())
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function getAllNextCount(Event $event): int
    {
        $from = new DateTime();

        return (int) $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('count(e.id)')
            ->from($this->getEntityName(), 'e')
            ->where('e.endDate >= :end_date AND e.id != :id AND e.place = :place')
            ->setParameter('end_date', $from->format('Y-m-d'))
            ->setParameter('id', $event->getId())
            ->setParameter('place', $event->getPlace()->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTopEventCount(Location $location): int
    {
        return (int) $this
            ->getTopEventBuilder($location)
            ->select('count(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getTopEventBuilder(Location $location): QueryBuilder
    {
        $du = new DateTime();
        $au = new DateTime('sunday this week');

        $qb = $this
            ->createQueryBuilder('e')
            ->where('e.endDate BETWEEN :from AND :to');

        if ($location->isCity()) {
            $qb
                ->andWhere('c.id = :city')
                ->setParameter('city', $location->getCity()->getId());
        } elseif ($location->isCountry()) {
            $qb
                ->andWhere('p.country = :country')
                ->setParameter('country', $location->getCountry()->getId());
        }

        return $qb
            ->setParameter('from', $du->format('Y-m-d'))
            ->setParameter('to', $au->format('Y-m-d'));
    }

    public function findTopEvents(Location $location, int $page = 1, int $limit = 7): array
    {
        return $this
            ->getTopEventBuilder($location)
            ->orderBy('e.endDate', Criteria::ASC)
            ->addOrderBy('e.participations', Criteria::DESC)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function findUpcomingEventsQueryBuilder(Location $location): QueryBuilder
    {
        $from = new DateTime();

        $qb = $this
            ->createQueryBuilder('e')
            ->where('e.endDate >= :from')
            ->setParameter('from', $from->format('Y-m-d'))
            ->orderBy('e.endDate', Criteria::ASC)
            ->addOrderBy('e.participations', Criteria::DESC);

        $this->buildLocationParameters($qb, $location);

        return $qb;
    }

    private function buildLocationParameters(QueryBuilder $queryBuilder, Location $location): void
    {
        if ($location->isCountry()) {
            $queryBuilder
                ->andWhere('p.country = :country')
                ->setParameter('country', $location->getCountry()->getId());
        } elseif ($location->isCity()) {
            $queryBuilder
                ->andWhere('p.city = :city')
                ->setParameter('city', $location->getCity()->getId());
        }
    }

    /**
     * @return string[]
     */
    public function getEventTypes(Location $location): array
    {
        $from = new DateTime();
        $from->modify(Event::INDEX_FROM);

        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('e.category')
            ->from($this->getEntityName(), 'e')
            ->join('e.place', 'p')
            ->where('e.category IS NOT NULL')
            ->andWhere('e.endDate >= :from');

        if ($location->isCity()) {
            $qb->andWhere('p.city = :city')
                ->setParameter('city', $location->getCity()->getId());
        } elseif ($location->isCountry()) {
            $qb->andWhere('p.city IS NULL')
                ->andWhere('p.country = :country')
                ->setParameter('country', $location->getCountry()->getId());
        }

        $results = $qb
            ->setParameter('from', $from->format('Y-m-d'))
            ->groupBy('e.category')
            ->getQuery()
            ->getArrayResult();

        return array_map('current', $results);
    }
}
