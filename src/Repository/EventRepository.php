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
use App\Contracts\MultipleEagerLoaderInterface;
use App\Dto\EventDto;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\Tag;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Manager\PreloadManager;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 *
 * @implements DtoFindableRepositoryInterface<EventDto, Event>
 * @implements MultipleEagerLoaderInterface<Event>
 *
 * @method Event|null find($id, $lockMode = null, $lockVersion = null)
 * @method Event|null findOneBy(array $criteria, array $orderBy = null)
 * @method Event[]    findAll()
 * @method Event[]    findBy(array $criteria, array $orderBy = null, int $limit = null, $offset = null)
 */
final class EventRepository extends ServiceEntityRepository implements DtoFindableRepositoryInterface, MultipleEagerLoaderInterface
{
    use DtoFindableTrait;

    public function __construct(
        ManagerRegistry $registry,
        private readonly PreloadManager $preloadManager,
    ) {
        parent::__construct($registry, Event::class);
    }

    public function loadAllEager(array $entities, array $context = []): void
    {
        $entityIds = array_map(static fn (Event $entity) => $entity->getId(), $entities);
        if ([] === $entityIds) {
            return;
        }

        $view = $context['view'] ?? null;

        $loadTimesheets = fn () => $this
            ->createQueryBuilder('e')
            ->select('PARTIAL e.{id}')
            ->addSelect('t')
            ->leftJoin('e.timesheets', 't')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $entityIds)
            ->getQuery()
            ->execute();

        $loadCategories = fn () => $this
            ->preloadManager
            ->preloadEntities(Tag::class, array_map(static fn (Event $entity) => $entity->getCategory()?->getId(), $entities));

        $loadThemes = fn () => $this
            ->createQueryBuilder('e')
            ->select('PARTIAL e.{id}')
            ->addSelect('t')
            ->leftJoin('e.themes', 't')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $entityIds)
            ->getQuery()
            ->execute();

        $loadPlaces = fn () => $this
            ->preloadManager
            ->preloadEntities(Place::class, array_map(static fn (Event $entity) => $entity->getPlace()?->getId(), $entities));

        $loadCities = fn () => $this
            ->preloadManager
            ->preloadEntities(City::class, array_map(static fn (Event $entity) => $entity->getPlace()?->getCity()?->getId(), $entities));

        $loadUsers = fn () => $this
            ->preloadManager
            ->preloadEntities(User::class, array_map(static fn (Event $entity) => $entity->getUser()?->getId(), $entities));

        if (\in_array($view, [
            'events:agenda:list',
            'events:widget:next-events',
            'events:widget:similar-events',
            'events:widget:top-events',
            'events:location:index',
            'events:user:list',
            'events:personal-space:list',
            'events:search:list',
        ], true)) {
            $loadTimesheets();
            $loadUsers();
        }

        if (\in_array($view, [
            'events:agenda:list',
            'events:widget:next-events',
            'events:widget:similar-events',
            'events:widget:top-events',
            'events:location:index',
            'events:user:list',
            'events:personal-space:list',
            'events:search:list',
            'elasticsearch:document',
        ], true)) {
            $loadPlaces();
            $loadCities();
        }

        if ('elasticsearch:document' === $view) {
            // Load themes before categories to avoid multiple queries for categories when themes are loaded
            $loadThemes();
            $loadCategories();
            // Sessions are indexed, one per timesheet
            $loadTimesheets();
        }

        if ('events:import' === $view) {
            // Merge path (EventEntityFactory::syncTimesheets / syncThemes) reads both
            // collections of every existing event: initialize them with one batched
            // query each instead of two lazy loads per event.
            $loadTimesheets();
            $loadThemes();
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return Event[]
     */
    public function findAllByDtos(array $dtos, bool $eager): array
    {
        $qb = $this->createQueryBuilder('e');

        $this->addDtosToQueryBuilder($qb, 'e', $dtos);

        $entityIdsWheres = [];
        foreach ($dtos as $dto) {
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

        /** @var Event[] $events */
        $events = $qb
            ->getQuery()
            ->execute();

        $this->loadAllEager($events, ['view' => 'events:import']);

        return $events;
    }

    /**
     * Rows whose family may have changed once the given events were imported: the
     * events themselves, their current canonical (they may have left its family) and
     * their current duplicates (they may have left theirs). Timesheets are not loaded:
     * most batches stop there, with no family to rebuild.
     *
     * @param int[] $eventIds
     *
     * @return Event[]
     */
    public function findFamilyCandidates(array $eventIds): array
    {
        if ([] === $eventIds) {
            return [];
        }

        // The canonicals first, by primary key: as a subquery OR-ed with the two other
        // conditions, it kept MySQL from using any index, and every import batch scanned
        // the whole duplicate_of index (~2.4M rows, ~4 s). Two plain INs merge both indexes.
        $canonicalIds = $this
            ->createQueryBuilder('d')
            ->select('IDENTITY(d.duplicateOf)')
            ->where('d.id IN (:ids)')
            ->andWhere('d.duplicateOf IS NOT NULL')
            ->setParameter('ids', $eventIds)
            ->getQuery()
            ->getSingleColumnResult();

        return $this
            ->createQueryBuilder('e')
            ->where('e.id IN (:ids)')
            ->orWhere('e.duplicateOf IN (:eventIds)')
            ->setParameter('ids', array_values(array_unique([...$eventIds, ...array_map(intval(...), $canonicalIds)])))
            ->setParameter('eventIds', $eventIds)
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Among the given identity hashes, the ones shared by at least two rows.
     *
     * @param string[] $hashes
     *
     * @return string[]
     */
    public function findSharedIdentityHashes(array $hashes): array
    {
        if ([] === $hashes) {
            return [];
        }

        $rows = $this
            ->createQueryBuilder('e')
            ->select('e.identityHash AS hash')
            ->where('e.identityHash IN (:hashes)')
            ->groupBy('e.identityHash')
            ->having('COUNT(e.id) > 1')
            ->setParameter('hashes', $hashes)
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'hash');
    }

    /**
     * Every member of the given families, in id order, timesheets not loaded.
     *
     * @param string[] $hashes
     *
     * @return Event[]
     */
    public function findAllByIdentityHashes(array $hashes): array
    {
        if ([] === $hashes) {
            return [];
        }

        return $this
            ->createQueryBuilder('e')
            ->where('e.identityHash IN (:hashes)')
            ->setParameter('hashes', $hashes)
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * The given canonicals and every row pointing at them, timesheets loaded in the
     * same query.
     *
     * @param int[] $canonicalIds
     *
     * @return Event[]
     */
    public function findFamiliesWithTimesheets(array $canonicalIds): array
    {
        if ([] === $canonicalIds) {
            return [];
        }

        return $this
            ->createQueryBuilder('e')
            ->addSelect('t')
            ->leftJoin('e.timesheets', 't')
            ->where('e.id IN (:ids)')
            ->orWhere('e.duplicateOf IN (:ids)')
            ->setParameter('ids', $canonicalIds)
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * The events of the search index, paged by id by App\Elasticsearch\Pager\EventPagerProvider.
     */
    public function createIsActiveQueryBuilder(): QueryBuilder
    {
        return $this
            ->createQueryBuilder('e')
            ->where('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false');
    }

    /**
     * The event with the place, city and country its URL and page are built from, in one query.
     * The city's parent is joined too: it targets the AdminZone inheritance root, which Doctrine
     * cannot proxy, so hydrating a city without it loads it with a query of its own.
     */
    public function findOneWithPlace(int $id): ?Event
    {
        return $this
            ->createQueryBuilder('e')
            ->addSelect('p', 'city', 'cityParent', 'country')
            ->leftJoin('e.place', 'p')
            ->leftJoin('p.city', 'city')
            ->leftJoin('city.parent', 'cityParent')
            ->leftJoin('p.country', 'country')
            ->where('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Highest id of the table, drafts and duplicates included.
     */
    public function findMaxId(): int
    {
        return (int) $this
            ->createQueryBuilder('e')
            ->select('MAX(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveEvents(): int
    {
        return (int) $this
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Published events ending on or after $since: the ones whose page is worth indexing.
     *
     * @return iterable<array>
     */
    public function findAllSiteMap(DateTimeInterface $since): iterable
    {
        return $this
            ->createQueryBuilder('e')
            ->select('e.slug, e.id, e.updatedAt, e.endDate, c.slug AS city_slug, c3.slug AS country_slug')
            ->join('e.place', 'p')
            ->leftJoin('p.city', 'c')
            ->join('p.country', 'c3')
            ->where('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->andWhere('e.endDate >= :since')
            ->setParameter('since', $since->format('Y-m-d'))
            ->orderBy('e.endDate', 'DESC')
            ->getQuery()
            ->toIterable();
    }

    public function findAllByUserQueryBuilder(User $user, ?string $q = null): QueryBuilder
    {
        $qb = $this
            ->createQueryBuilder('e')
            ->where('e.user = :user')
            ->setParameter('user', $user->getId())
            ->orderBy('e.id', 'DESC');

        if ($q) {
            $qb->andWhere('e.name LIKE :q OR e.placeName LIKE :q OR e.placeCity LIKE :q OR e.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        return $qb;
    }

    public function getCountryEvents(): array
    {
        $from = new DateTimeImmutable();

        return $this
            ->createQueryBuilder('e')
            ->select('c.displayName, c.atDisplayName, c.slug, COUNT(e.id) AS events')
            ->join('e.place', 'p')
            ->join('p.country', 'c')
            ->where('e.endDate >= :from')
            ->setParameter('from', $from->format('Y-m-d'))
            ->orderBy('events', 'DESC')
            ->groupBy('c.id')
            ->getQuery()
            ->enableResultCache(3600) // 1 hour
            ->getScalarResult()
        ;
    }

    /**
     * Import sources used by events, sorted by name. Events created by hand (no source) are left out.
     *
     * @return list<string>
     */
    public function findDistinctFromData(): array
    {
        return $this
            ->createQueryBuilder('e')
            ->select('DISTINCT e.fromData')
            ->where('e.fromData IS NOT NULL')
            ->orderBy('e.fromData', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
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
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(e) as eventsCount, p.name')
            ->from(UserEvent::class, 'ue')
            ->join('ue.event', 'e')
            ->join('e.place', 'p')
            ->where('ue.user = :user')
            ->groupBy('p.name')
            ->orderBy('eventsCount', 'DESC')
            ->setParameter('user', $user->getId())
            ->setFirstResult(0)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAllNextEvents(User $user, bool $isNext = true): QueryBuilder
    {
        return $this
            ->createQueryBuilder('e')
            ->join('e.userEvents', 'cal')
            ->where('cal.user = :user')
            ->andWhere('e.draft = false')
            ->andWhere('e.endDate ' . ($isNext ? '>=' : '<') . ' :start_date')
            ->orderBy('e.endDate', $isNext ? 'ASC' : 'DESC')
            ->setParameter('user', $user->getId())
            ->setParameter('start_date', date('Y-m-d'));
    }

    public function getUserFavoriteEventsCount(User $user): int
    {
        return (int) $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(u)')
            ->from(UserEvent::class, 'ue')
            ->join('ue.user', 'u')
            ->where('ue.user = :user')
            ->setParameter('user', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getParticipationTrendsCount(Event $event): int
    {
        return $this->getTrendsCount($event);
    }

    public function getInterestTrendsCount(Event $event): int
    {
        return $this->getTrendsCount($event, false);
    }

    protected function getTrendsCount(Event $event, bool $isParticipation = true): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(u)')
            ->from(UserEvent::class, 'ue')
            ->join('ue.user', 'u')
            ->where('ue.event = :event')
            ->andWhere(($isParticipation ? 'ue.going' : 'ue.wish') . ' = true')
            ->setParameter('event', $event->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllSimilarsQueryBuilder(Event $event): QueryBuilder
    {
        $qb = $this
            ->createQueryBuilder('e')
            ->where('e.startDate = :from')
            ->andWhere('e.id != :id')
            ->andWhere('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->setParameter('from', $event->getStartDate()->format('Y-m-d'))
            ->setParameter('id', $event->getId())
            ->orderBy('e.name', 'ASC');

        if (null !== $event->getPlace()->getCity()) {
            $qb
                ->join('e.place', 'p')
                ->andWhere('p.city = :city')
                ->setParameter('city', $event->getPlace()->getCity()->getId());
        } elseif (null !== $event->getPlace()->getCountry()) {
            $qb
                ->join('e.place', 'p')
                ->andWhere('p.country = :country')
                ->setParameter('country', $event->getPlace()->getCountry()->getId());
        }

        return $qb;
    }

    public function findAllNextQueryBuilder(Event $event): QueryBuilder
    {
        $from = new DateTimeImmutable();

        return $this
            ->createQueryBuilder('e')
            ->where('e.endDate >= :end_date AND e.id != :id AND e.place = :place AND e.duplicateOf IS NULL AND e.draft = false')
            ->orderBy('e.endDate', 'ASC')
            ->setParameter('end_date', $from->format('Y-m-d'))
            ->setParameter('id', $event->getId())
            ->setParameter('place', $event->getPlace()->getId());
    }

    public function findTopEventsQueryBuilder(Location $location): QueryBuilder
    {
        $du = new DateTimeImmutable();
        $au = new DateTimeImmutable('sunday this week');

        $qb = $this
            ->createQueryBuilder('e')
            ->where('e.endDate BETWEEN :from AND :to')
            ->andWhere('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->orderBy('e.endDate', 'ASC')
            ->addOrderBy('e.participations', 'DESC');

        if ($location->isCity()) {
            $qb
                ->join('e.place', 'p')
                ->join('p.city', 'c')
                ->andWhere('c.id = :city')
                ->setParameter('city', $location->getCity()->getId());
        } elseif ($location->isCountry()) {
            $qb
                ->join('e.place', 'p')
                ->andWhere('p.country = :country')
                ->setParameter('country', $location->getCountry()->getId());
        }

        return $qb
            ->setParameter('from', $du->format('Y-m-d'))
            ->setParameter('to', $au->format('Y-m-d'));
    }

    public function findUpcomingEvents(Location $location): QueryBuilder
    {
        $from = new DateTimeImmutable();

        $qb = $this
            ->createQueryBuilder('e')
            ->where('e.endDate >= :from')
            ->andWhere('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->setParameter('from', $from->format('Y-m-d'))
            ->orderBy('e.endDate', 'ASC')
            ->addOrderBy('e.participations', 'DESC');

        $this->buildLocationParameters($qb, $location);

        return $qb;
    }

    private function buildLocationParameters(QueryBuilder $queryBuilder, Location $location): void
    {
        if ($location->isCountry()) {
            $queryBuilder
                ->join('e.place', 'p')
                ->andWhere('p.country = :country')
                ->setParameter('country', $location->getCountry()->getId());
        } elseif ($location->isCity()) {
            $queryBuilder
                ->join('e.place', 'p')
                ->andWhere('p.city = :city')
                ->setParameter('city', $location->getCity()->getId());
        }
    }

    /**
     * @return Tag[]
     */
    public function getEventTypes(Location $location): array
    {
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('c')
            ->from(Tag::class, 'c')
            ->join(Event::class, 'e', 'WITH', 'e.category = c.id')
        ;

        if ($location->isCity()) {
            $qb
                ->join('e.place', 'p')
                ->andWhere('p.city = :city')
                ->setParameter('city', $location->getCity()->getId());
        } elseif ($location->isCountry()) {
            $qb
                ->join('e.place', 'p')
                ->andWhere('p.city IS NULL')
                ->andWhere('p.country = :country')
                ->setParameter('country', $location->getCountry()->getId());
        }

        return $qb
            ->groupBy('c')
            ->getQuery()
            ->execute();
    }
}
