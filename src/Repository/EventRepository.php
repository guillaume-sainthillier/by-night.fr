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
use App\Entity\Country;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\Tag;
use App\Entity\UpcomingCategory;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Enum\DuplicateReason;
use App\Enum\EventStatus;
use App\Enum\PersonalEventFilter;
use App\Manager\PreloadManager;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
use SortDirection;

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

    /** How long the counts and highlights of the portals may lag behind the events */
    private const int PORTAL_CACHE_TTL = 3600;

    /** How the price of a free event starts, lowercased (see countUserCalendarHabits()) */
    private const array FREE_PRICE_PREFIXES = ['gratuit', 'entrée gratuite', 'entrée libre'];

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
            'events:widget:next-events',
            'events:widget:similar-events',
            'events:user:list',
            'events:personal-space:list',
            'events:search:list',
        ], true)) {
            $loadTimesheets();
            $loadUsers();
        }

        if (\in_array($view, [
            'events:widget:next-events',
            'events:widget:similar-events',
            'events:user:list',
            'events:personal-space:list',
            'events:search:list',
            'elasticsearch:document',
        ], true)) {
            $loadPlaces();
            $loadCities();
        }

        // Cards and rows of the portals and the agenda: dates, venue, city and category, no author
        if (\in_array($view, ['events:portal:list', 'events:agenda:list'], true)) {
            $loadTimesheets();
            $loadPlaces();
            $loadCities();
            $loadCategories();
        }

        // The organizer's list names the category under each event
        if ('events:personal-space:list' === $view) {
            $loadCategories();
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

        if ('admin:index' === $view) {
            $loadPlaces();
            $loadUsers();
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
     * The events imported from these records of a source.
     *
     * @param list<string> $externalIds
     *
     * @return Event[]
     */
    public function findByExternalIds(string $externalOrigin, array $externalIds): array
    {
        if ([] === $externalIds) {
            return [];
        }

        return $this
            ->createQueryBuilder('e')
            ->where('e.externalOrigin = :externalOrigin')
            ->andWhere('e.externalId IN (:externalIds)')
            ->setParameter('externalOrigin', $externalOrigin)
            // Numeric ids must stay strings, see DtoFindableTrait
            ->setParameter('externalIds', $externalIds, ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();
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
            ->orderBy('e.id', SortDirection::Ascending)
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
            ->orderBy('e.id', SortDirection::Ascending)
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
            ->orderBy('e.id', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * The venues where at least two sources list an event that is not over: the only ones where the same show can
     * be imported twice (App\Import\CrossSource\CrossSourceDuplicateFinder).
     *
     * @return list<int>
     */
    public function findPlaceIdsListedBySeveralSources(DateTimeImmutable $from): array
    {
        $ids = $this
            ->createQueryBuilder('e')
            ->select('IDENTITY(e.place) AS placeId')
            ->where('e.fromData IS NOT NULL')
            ->andWhere('e.endDate >= :from')
            ->andWhere('e.place IS NOT NULL')
            ->groupBy('e.place')
            ->having('COUNT(DISTINCT e.fromData) > 1')
            ->orderBy('placeId', SortDirection::Ascending)
            ->setParameter('from', $from->format('Y-m-d'))
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(intval(...), $ids);
    }

    /**
     * The imported events of these venues that are not over, as plain rows: enough to compare their titles and
     * dates before loading the few that look alike, and to tell the rows the comparison leaves out (duplicates linked
     * by hand, events taken back by their source) from those it covers.
     *
     * @param list<int> $placeIds
     *
     * @return list<array{id: int, placeId: int, fromData: string, name: string|null, startDate: DateTimeImmutable|null, endDate: DateTimeImmutable|null, placeName: string|null, cityName: string|null, duplicateOfId: int|null, duplicateReason: DuplicateReason|null, identityHash: string|null, canonicalIdentityHash: string|null, status: EventStatus|null, draft: bool}>
     */
    public function findImportedRowsAtPlaces(array $placeIds, DateTimeImmutable $from): array
    {
        if ([] === $placeIds) {
            return [];
        }

        /** @var list<array{id: int, placeId: int|string, fromData: string, name: string|null, startDate: DateTimeImmutable|null, endDate: DateTimeImmutable|null, placeName: string|null, cityName: string|null, duplicateOfId: int|string|null, duplicateReason: DuplicateReason|null, identityHash: string|null, canonicalIdentityHash: string|null, status: EventStatus|null, draft: bool|null}> $rows */
        $rows = $this
            ->createQueryBuilder('e')
            ->select('e.id, IDENTITY(e.place) AS placeId, e.fromData, e.name, e.startDate, e.endDate, p.name AS placeName, c.name AS cityName')
            ->addSelect('IDENTITY(e.duplicateOf) AS duplicateOfId, e.duplicateReason, e.identityHash, d.identityHash AS canonicalIdentityHash, e.status, e.draft')
            ->join('e.place', 'p')
            ->leftJoin('p.city', 'c')
            ->leftJoin('e.duplicateOf', 'd')
            ->where('e.place IN (:places)')
            ->andWhere('e.fromData IS NOT NULL')
            ->andWhere('e.endDate >= :from')
            ->orderBy('e.id', SortDirection::Ascending)
            ->setParameter('places', $placeIds)
            ->setParameter('from', $from->format('Y-m-d'))
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'placeId' => (int) $row['placeId'],
            'duplicateOfId' => null !== $row['duplicateOfId'] ? (int) $row['duplicateOfId'] : null,
            'draft' => (bool) $row['draft'],
        ] + $row, $rows);
    }

    /**
     * These events, their timesheets loaded in the same query.
     *
     * @param list<int> $ids
     *
     * @return Event[]
     */
    public function findWithTimesheets(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this
            ->createQueryBuilder('e')
            ->addSelect('t', 'p')
            ->leftJoin('e.timesheets', 't')
            ->leftJoin('e.place', 'p')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
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
     * The events waiting for the image their source gives: never stored, or lost, and not taken down on request
     * (EventImageRemover), which is not downloaded again.
     *
     * @param DateTimeInterface|null $endingFrom only the events not over by that day
     */
    public function createWaitingForImageQueryBuilder(?DateTimeInterface $endingFrom = null): QueryBuilder
    {
        $queryBuilder = $this
            ->createQueryBuilder('e')
            ->where('e.url IS NOT NULL')
            ->andWhere("e.imageSystem.name IS NULL OR e.imageSystem.name = ''")
            ->andWhere('e.imageRemovedAt IS NULL');

        if (null !== $endingFrom) {
            $queryBuilder
                ->andWhere('e.endDate >= :ending_from')
                ->setParameter('ending_from', $endingFrom->format('Y-m-d'));
        }

        return $queryBuilder;
    }

    public function countWaitingForImage(?DateTimeInterface $endingFrom = null): int
    {
        return (int) $this->createWaitingForImageQueryBuilder($endingFrom)
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The event whose picture, from its member or its source, is stored under that file name.
     */
    public function findOneByImageName(string $name): ?Event
    {
        /* @var Event|null */
        return $this
            ->createQueryBuilder('e')
            ->where('e.image.name = :name OR e.imageSystem.name = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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
     * What the members published themselves, imports aside: their events and how many members they come from.
     *
     * @return array{events: int, organizers: int}
     */
    public function getMemberTotals(): array
    {
        $totals = $this
            ->createQueryBuilder('e')
            ->select('COUNT(e.id) AS events', 'COUNT(DISTINCT IDENTITY(e.user)) AS organizers')
            ->where('e.user IS NOT NULL')
            ->andWhere('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->getQuery()
            // Reads every member event (~0.4 s): a key figure of the page, one day old at most
            ->enableResultCache(86400) // 1 day
            ->getSingleResult();

        return ['events' => (int) $totals['events'], 'organizers' => (int) $totals['organizers']];
    }

    /**
     * Published events ending on or after $since: the ones whose page is worth indexing.
     *
     * @return CursorPagination<array{slug: string, id: int, updatedAt: ?DateTimeImmutable, endDate: DateTimeImmutable, city_slug: ?string, country_slug: string}>
     */
    public function findAllSiteMap(DateTimeInterface $since, int $batchSize): CursorPagination
    {
        $queryBuilder = $this
            ->createQueryBuilder('e')
            ->select('e.slug, e.id, e.updatedAt, e.endDate, c.slug AS city_slug, c3.slug AS country_slug')
            ->join('e.place', 'p')
            ->leftJoin('p.city', 'c')
            ->join('p.country', 'c3')
            ->where('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->andWhere('e.endDate >= :since')
            ->setParameter('since', $since->format('Y-m-d'));

        // Newest first on the primary key: a cursor on (endDate, id) sorts the whole remaining
        // date range again on every page, no index serving that order (334 s against 8 s here)
        return new CursorPagination(
            $queryBuilder,
            new OrderConfigurations(new OrderConfiguration('e.id', static fn (array $event): int => $event['id'], orderAscending: false)),
            $batchSize,
            fetchJoinCollection: false,
        );
    }

    public function findAllByUserQueryBuilder(User $user, ?string $q = null, ?PersonalEventFilter $filter = null): QueryBuilder
    {
        $qb = $this
            ->createQueryBuilder('e')
            ->where('e.user = :user')
            ->setParameter('user', $user->getId())
            ->orderBy('e.id', SortDirection::Descending);

        if ($q) {
            $qb->andWhere('e.name LIKE :q OR e.placeName LIKE :q OR e.placeCity LIKE :q OR e.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if (null !== $filter) {
            $qb->andWhere(self::personalEventCondition($filter));
        }

        return $qb;
    }

    /**
     * How many events the user created ("total"), and how many of them each filter of their list keeps (keyed by the
     * filter's value), in one query.
     *
     * @return array<string, int>
     */
    public function countByUserAndFilter(User $user): array
    {
        $qb = $this
            ->createQueryBuilder('e')
            ->select('COUNT(e.id) AS total')
            ->where('e.user = :user')
            ->setParameter('user', $user->getId());

        foreach (PersonalEventFilter::cases() as $filter) {
            // Not aliased by the bare value: "hidden" is a DQL keyword
            $qb->addSelect(\sprintf('SUM(CASE WHEN %s THEN 1 ELSE 0 END) AS %sCount', self::personalEventCondition($filter), $filter->value));
        }

        /** @var array<string, int|string|null> $row SUM() is NULL when the user has no event */
        $row = $qb->getQuery()->getSingleResult();

        $counts = ['total' => (int) $row['total']];
        foreach (PersonalEventFilter::cases() as $filter) {
            $counts[$filter->value] = (int) $row[$filter->value . 'Count'];
        }

        return $counts;
    }

    /**
     * The DQL condition of a filter of an organizer's list, shared by the list and its counts so that they agree.
     */
    private static function personalEventCondition(PersonalEventFilter $filter): string
    {
        return match ($filter) {
            PersonalEventFilter::Visible => 'e.draft = false',
            PersonalEventFilter::Hidden => 'e.draft = true',
            PersonalEventFilter::Cancelled => \sprintf("e.status = '%s'", EventStatus::Cancelled->value),
        };
    }

    /**
     * The countries with published events to come, the busiest first, as last counted by UpcomingEventCounter.
     *
     * @return list<array{id: string, displayName: string, atDisplayName: string, slug: string, events: int|string}> id is the ISO 3166 code
     */
    public function getCountryEvents(): array
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('c.id, c.displayName, c.atDisplayName, c.slug, c.upcomingEvents AS events')
            ->from(Country::class, 'c')
            ->where('c.upcomingEvents > 0')
            ->orderBy('c.upcomingEvents', SortDirection::Descending)
            ->getQuery()
            ->getScalarResult();
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
            ->orderBy('e.fromData', SortDirection::Ascending)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * The number of published events of a member's calendar on each day, by the day they start: a night out that ends
     * after midnight (over a quarter of the calendars' events) belongs to the evening it began.
     *
     * @return array<string, int> keyed by date (Y-m-d), in order
     */
    public function countUserEventsByDay(User $user): array
    {
        /** @var list<array{day: DateTimeInterface|string, events: int|string}> $rows */
        $rows = $this->createUserCalendarQueryBuilder($user)
            ->select('e.startDate AS day', 'COUNT(e.id) AS events')
            ->andWhere('e.startDate IS NOT NULL')
            ->groupBy('e.startDate')
            ->orderBy('e.startDate', SortDirection::Ascending)
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $day = $row['day'] instanceof DateTimeInterface ? $row['day']->format('Y-m-d') : $row['day'];
            $counts[$day] = (int) $row['events'];
        }

        return $counts;
    }

    /**
     * The cities of the venues of a member's published events, the busiest first.
     *
     * @return list<array{name: string, slug: string, events: int, firstYear: int, lastYear: int}>
     */
    public function findUserCities(User $user): array
    {
        /** @var list<array{name: string, slug: string, events: int|string, first: string, last: string}> $rows */
        $rows = $this->createUserCalendarQueryBuilder($user)
            ->select('c.name', 'c.slug', 'COUNT(e.id) AS events', 'MIN(e.startDate) AS first', 'MAX(e.startDate) AS last')
            ->join('e.place', 'p')
            ->join('p.city', 'c')
            ->groupBy('c.id')
            ->orderBy('events', SortDirection::Descending)
            ->addOrderBy('c.name', SortDirection::Ascending)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): array => [
            'name' => $row['name'],
            'slug' => $row['slug'],
            'events' => (int) $row['events'],
            'firstYear' => (int) substr($row['first'], 0, 4),
            'lastYear' => (int) substr($row['last'], 0, 4),
        ], $rows);
    }

    /**
     * The venues of a member's published events, the busiest first.
     *
     * @return list<array{name: string, slug: string, locationSlug: string, cityName: string|null, events: int}>
     */
    public function findUserPlaces(User $user, int $limit = 5): array
    {
        /** @var list<array{name: string, slug: string, cityName: string|null, citySlug: string|null, countrySlug: string|null, events: int|string}> $rows */
        $rows = $this->createUserCalendarQueryBuilder($user)
            ->select('p.name', 'p.slug', 'c.name AS cityName', 'c.slug AS citySlug', 'co.slug AS countrySlug', 'COUNT(e.id) AS events')
            ->join('e.place', 'p')
            ->leftJoin('p.city', 'c')
            ->leftJoin('p.country', 'co')
            // c and co are one row per venue: grouped by too for MySQL's ONLY_FULL_GROUP_BY
            ->groupBy('p.id, c.id, co.id')
            ->orderBy('events', SortDirection::Descending)
            ->addOrderBy('p.name', SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): array => [
            'name' => $row['name'],
            'slug' => $row['slug'],
            // As Place::getLocationSlug(): the agenda of the venue's city, else of its country
            'locationSlug' => $row['citySlug'] ?? $row['countrySlug'] ?? 'unknown',
            'cityName' => $row['cityName'],
            'events' => (int) $row['events'],
        ], $rows);
    }

    /**
     * The categories of a member's published events, the busiest first.
     *
     * @return list<array{id: int, name: string, events: int, firstYear: int, lastYear: int}>
     */
    public function findUserCategories(User $user): array
    {
        /** @var list<array{id: int|string, name: string, events: int|string, first: string, last: string}> $rows */
        $rows = $this->createUserCalendarQueryBuilder($user)
            ->select('t.id', 't.name', 'COUNT(e.id) AS events', 'MIN(e.startDate) AS first', 'MAX(e.startDate) AS last')
            ->join('e.category', 't')
            ->groupBy('t.id')
            ->orderBy('events', SortDirection::Descending)
            ->addOrderBy('t.name', SortDirection::Ascending)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'events' => (int) $row['events'],
            'firstYear' => (int) substr($row['first'], 0, 4),
            'lastYear' => (int) substr($row['last'], 0, 4),
        ], $rows);
    }

    /**
     * The themes a member comes back to in some categories of their published events: the ones of at least two of
     * their events, the busiest first. The themes are free text from the sources, one-offs are often noise.
     *
     * @param list<int> $categoryIds
     *
     * @return array<int, list<string>> the names of the themes, keyed by category id
     */
    public function findUserThemesByCategory(User $user, array $categoryIds, int $limit = 3): array
    {
        if ([] === $categoryIds) {
            return [];
        }

        /** @var list<array{category: int|string, name: string}> $rows */
        $rows = $this->createUserCalendarQueryBuilder($user)
            ->select('c.id AS category', 'th.name', 'COUNT(e.id) AS HIDDEN events')
            ->join('e.category', 'c')
            ->join('e.themes', 'th')
            ->andWhere('c.id IN (:categories)')
            // A source often repeats the category among the themes
            ->andWhere('th.id <> c.id')
            ->groupBy('c.id, th.id')
            ->having('COUNT(e.id) >= 2')
            ->orderBy('events', SortDirection::Descending)
            ->addOrderBy('th.name', SortDirection::Ascending)
            ->setParameter('categories', $categoryIds)
            ->getQuery()
            ->getScalarResult();

        $themes = [];
        foreach ($rows as $row) {
            $category = (int) $row['category'];
            if (\count($themes[$category] ?? []) < $limit) {
                $themes[$category][] = $row['name'];
            }
        }

        return $themes;
    }

    /**
     * How a member fills their calendar: the published events they added $aheadDays or more before they started, and
     * the free ones.
     *
     * An event is free when its price text starts with "Gratuit", "Entrée gratuite", "Entrée libre" or is "Free", with
     * no amount in euros: "4 € - gratuit pour les moins de 18 ans" is not. The price is free text from the sources.
     *
     * @return array{addedAhead: int, free: int}
     */
    public function countUserCalendarHabits(User $user, int $aheadDays): array
    {
        $isFree = \sprintf(
            "(%s OR LOWER(e.prices) = 'free') AND e.prices NOT LIKE '%%€%%'",
            implode(' OR ', array_map(static fn (string $prefix): string => \sprintf("LOWER(e.prices) LIKE '%s%%'", $prefix), self::FREE_PRICE_PREFIXES)),
        );

        /** @var array{addedAhead: int|string|null, free: int|string|null} $row SUM() is NULL without any event */
        $row = $this->createUserCalendarQueryBuilder($user)
            ->select(
                'SUM(CASE WHEN DATE_DIFF(e.startDate, ue.createdAt) >= :aheadDays THEN 1 ELSE 0 END) AS addedAhead',
                \sprintf('SUM(CASE WHEN %s THEN 1 ELSE 0 END) AS free', $isFree),
            )
            ->andWhere('e.startDate IS NOT NULL')
            ->setParameter('aheadDays', $aheadDays)
            ->getQuery()
            ->getSingleResult();

        return ['addedAhead' => (int) $row['addedAhead'], 'free' => (int) $row['free']];
    }

    /**
     * @return int the number of venues of a member's published events
     */
    public function countUserPlaces(User $user): int
    {
        return (int) $this->createUserCalendarQueryBuilder($user)
            ->select('COUNT(DISTINCT p.id)')
            ->join('e.place', 'p')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The published events of a member's calendar (the events they go to or are interested in), as "e".
     */
    private function createUserCalendarQueryBuilder(User $user): QueryBuilder
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->from(UserEvent::class, 'ue')
            ->join('ue.event', 'e')
            ->where('ue.user = :user')
            ->andWhere(self::inCalendar('ue'))
            ->andWhere('e.draft = false')
            ->setParameter('user', $user->getId());
    }

    /**
     * A "J'y vais" taken back keeps its row, neither going nor interested (EventParticipationManager::participate()):
     * out of the calendar.
     */
    private static function inCalendar(string $alias): string
    {
        return \sprintf('(%1$s.going = true OR %1$s.wish = true)', $alias);
    }

    public function findAllNextEvents(User $user, bool $isNext = true): QueryBuilder
    {
        return $this
            ->createQueryBuilder('e')
            ->join('e.userEvents', 'cal')
            ->where('cal.user = :user')
            ->andWhere(self::inCalendar('cal'))
            ->andWhere('e.draft = false')
            ->andWhere('e.endDate ' . ($isNext ? '>=' : '<') . ' :start_date')
            ->orderBy('e.endDate', $isNext ? SortDirection::Ascending : SortDirection::Descending)
            ->setParameter('user', $user->getId())
            ->setParameter('start_date', date('Y-m-d'));
    }

    /**
     * @return int the number of published events of a member's calendar: the ones their profile lists
     */
    public function getUserFavoriteEventsCount(User $user): int
    {
        return (int) $this->createUserCalendarQueryBuilder($user)
            ->select('COUNT(ue.id)')
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
            ->orderBy('e.name', SortDirection::Ascending);

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
            ->orderBy('e.endDate', SortDirection::Ascending)
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
            ->orderBy('e.endDate', SortDirection::Ascending)
            ->addOrderBy('e.participations', SortDirection::Descending);

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
            ->orderBy('e.endDate', SortDirection::Ascending)
            ->addOrderBy('e.participations', SortDirection::Descending);

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
     * The categories of the events to come in a city, the most frequent first: the shortcuts of the search panel.
     *
     * @return list<array{0: Tag, events: int|string}> each category, and its number of events to come
     */
    public function findUpcomingCategoriesOfCity(City $city, int $limit): array
    {
        return $this->findUpcomingCategories(new Location()->setCity($city), $limit);
    }

    /**
     * The categories of the events to come in a city or a country, the most frequent first, as last counted by
     * UpcomingEventCounter.
     *
     * @return list<array{0: Tag, events: int|string}> each category, and its number of events to come
     */
    public function findUpcomingCategories(Location $location, int $limit): array
    {
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('t', 'uc.events AS events')
            ->from(Tag::class, 't')
            ->join(UpcomingCategory::class, 'uc', Join::ON, 'uc.tag = t')
            ->orderBy('uc.events', SortDirection::Descending)
            ->addOrderBy('t.name', SortDirection::Ascending)
            ->setMaxResults($limit);

        if (null !== $city = $location->getCity()) {
            $qb
                ->where('uc.city = :city')
                ->setParameter('city', $city->getId());
        } elseif (null !== $country = $location->getCountry()) {
            $qb
                ->where('uc.country = :country')
                ->setParameter('country', $country->getId());
        } else {
            // Only cities and countries are counted
            return [];
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * The events of the next seven days that have a picture, the most followed first: the "À l'affiche" cards of the
     * portals.
     *
     * @return Event[]
     */
    public function findHighlights(Location $location, int $limit): array
    {
        $from = new DateTimeImmutable('today');

        // The ids first: event_popular_idx covers this query, so MySQL ranks the upcoming events without reading
        // them. Selecting the events themselves would load every upcoming row (~180k) to test its start date, as
        // MySQL pushes no condition down to an index over a virtual column (has_image)
        $qb = $this
            ->createQueryBuilder('e')
            ->select('e.id')
            ->join('e.place', 'p')
            ->andWhere('e.startDate <= :to')
            ->andWhere('e.hasImage = true')
            ->setParameter('to', $from->modify('+6 days')->format('Y-m-d'))
            ->orderBy('e.participations', SortDirection::Descending)
            ->addOrderBy('e.endDate', SortDirection::Ascending)
            ->addOrderBy('e.id', SortDirection::Ascending)
            ->setMaxResults($limit);

        /** @var list<int> $ids */
        $ids = $this
            ->whereUpcoming($qb, $location)
            ->getQuery()
            ->enableResultCache(self::PORTAL_CACHE_TTL)
            ->getSingleColumnResult();

        if ([] === $ids) {
            return [];
        }

        /** @var Event[] $events */
        $events = $this
            ->createQueryBuilder('e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->enableResultCache(self::PORTAL_CACHE_TTL)
            ->getResult();

        $ranks = array_flip($ids);
        usort($events, static fn (Event $a, Event $b): int => $ranks[$a->getId()] <=> $ranks[$b->getId()]);

        $this->loadAllEager($events, ['view' => 'events:portal:list']);

        return $events;
    }

    /**
     * The cities of a country with the most events to come.
     *
     * @return list<array{0: City, events: int|string}> each city, and its number of events to come
     */
    public function findUpcomingCitiesOfCountry(Country $country, int $limit): array
    {
        return $this
            ->createUpcomingCitiesQueryBuilder($limit)
            ->andWhere('c.country = :country')
            ->setParameter('country', $country->getId())
            ->getQuery()
            ->getResult();
    }

    /**
     * The countries with events to come, as their cards list them on the home and country pages: the featured ones
     * first, then as ranked in the back office, then the busiest; each with its busiest cities.
     *
     * @return list<array{0: Country, events: int|string, cities: list<City>}> each country, its number of events to come, and its busiest cities
     */
    public function findUpcomingCountries(int $cities, ?Country $except = null): array
    {
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('c', 'c.upcomingEvents AS events')
            ->addSelect('CASE WHEN c.displayOrder IS NULL THEN 1 ELSE 0 END AS HIDDEN unranked')
            ->from(Country::class, 'c')
            ->where('c.upcomingEvents > 0')
            ->orderBy('c.featured', SortDirection::Descending)
            ->addOrderBy('unranked', SortDirection::Ascending)
            ->addOrderBy('c.displayOrder', SortDirection::Ascending)
            ->addOrderBy('c.upcomingEvents', SortDirection::Descending)
            ->addOrderBy('c.displayName', SortDirection::Ascending);

        if (null !== $except) {
            $qb
                ->andWhere('c.id <> :except')
                ->setParameter('except', $except->getId());
        }

        /** @var list<array{0: Country, events: int|string}> $countries */
        $countries = $qb->getQuery()->getResult();

        return array_map(fn (array $row): array => $row + [
            'cities' => array_column($this->findUpcomingCitiesOfCountry($row[0], $cities), 0),
        ], $countries);
    }

    /**
     * The cities around a city (about 100 km) with the most events to come, the city itself left out.
     *
     * @return list<array{0: City, events: int|string}> each city, and its number of events to come
     */
    public function findUpcomingCitiesAround(City $city, int $limit): array
    {
        // A bounding box rather than a distance: it reads the city coordinates as they are, and the ranking
        // is by events, not by distance
        $latitude = (float) $city->getLatitude();
        $longitude = (float) $city->getLongitude();
        $latitudeDelta = 0.9;
        $longitudeDelta = $latitudeDelta / max(0.2, cos(deg2rad($latitude)));

        return $this
            ->createUpcomingCitiesQueryBuilder($limit)
            ->andWhere('c.id <> :city')
            ->andWhere('c.latitude BETWEEN :minLatitude AND :maxLatitude')
            ->andWhere('c.longitude BETWEEN :minLongitude AND :maxLongitude')
            ->setParameter('city', $city->getId())
            ->setParameter('minLatitude', $latitude - $latitudeDelta)
            ->setParameter('maxLatitude', $latitude + $latitudeDelta)
            ->setParameter('minLongitude', $longitude - $longitudeDelta)
            ->setParameter('maxLongitude', $longitude + $longitudeDelta)
            ->getQuery()
            ->getResult();
    }

    /**
     * The events to come, and the cities and venues that host them: the key figures of the home page.
     *
     * @return array{events: int, cities: int, places: int}
     */
    public function getUpcomingTotals(): array
    {
        $places = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('COALESCE(SUM(p.upcomingEvents), 0) AS events', 'COUNT(p.id) AS places')
            ->from(Place::class, 'p')
            ->where('p.upcomingEvents > 0')
            ->getQuery()
            ->getSingleResult();

        $cities = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(City::class, 'c')
            ->where('c.upcomingEvents > 0')
            ->getQuery()
            ->getSingleScalarResult();

        return ['events' => (int) $places['events'], 'cities' => (int) $cities, 'places' => (int) $places['places']];
    }

    /**
     * The published events to come of each venue that has some: what UpcomingEventCounter stores on places.
     *
     * @param list<int>|null $placeIds the venues to count, null for all of them
     *
     * @return array<int, int> number of events to come, by place id
     */
    public function countUpcomingByPlace(?array $placeIds = null): array
    {
        // The place is not joined: event_upcoming_idx holds its id, so the index alone answers (70 ms instead of 300)
        $qb = $this
            ->createQueryBuilder('e')
            ->select('IDENTITY(e.place) AS place', 'COUNT(e.id) AS events')
            ->andWhere('e.place IS NOT NULL')
            ->groupBy('e.place');

        if (null !== $placeIds) {
            $qb
                ->andWhere('e.place IN (:places)')
                ->setParameter('places', $placeIds);
        }

        $rows = $this->whereUpcoming($qb)->getQuery()->getScalarResult();

        return array_map(intval(...), array_column($rows, 'events', 'place'));
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, list<string>> the types stored on these events, by id (the events without any left out):
     *                                  what AgendaTypeClassifier compares its findings with
     */
    public function findAgendaTypesOf(array $ids): array
    {
        $types = [];
        foreach (array_chunk($ids, 1_000) as $chunk) {
            $types += self::agendaTypes($this
                ->createQueryBuilder('e')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $chunk));
        }

        return $types;
    }

    /**
     * @return array<int, list<string>>
     */
    private static function agendaTypes(QueryBuilder $qb): array
    {
        $rows = $qb
            ->select('e.id', 'e.agendaTypes')
            ->andWhere('e.agendaTypes IS NOT NULL')
            ->getQuery()
            ->getScalarResult();

        // A scalar result leaves the column as stored: "concert,famille"
        return array_map(static fn (string $types): array => explode(',', $types), array_column($rows, 'agendaTypes', 'id'));
    }

    /**
     * The published events to come that have a category, counted by the city and the country of their venue: what
     * UpcomingEventCounter stores as UpcomingCategory rows.
     *
     * @param list<int|string>|null $cityIds    with $countryIds, keeps the venues in these cities or in these countries only
     * @param list<int|string>|null $countryIds
     *
     * @return list<array{city: int|string|null, country: string|null, category: int|string, events: int|string}>
     */
    public function countUpcomingCategoriesByZone(?array $cityIds = null, ?array $countryIds = null): array
    {
        $qb = $this
            ->createQueryBuilder('e')
            ->select('IDENTITY(p.city) AS city', 'IDENTITY(p.country) AS country', 'IDENTITY(e.category) AS category', 'COUNT(e.id) AS events')
            ->join('e.place', 'p')
            ->andWhere('e.category IS NOT NULL')
            ->groupBy('p.city', 'p.country', 'e.category');

        if (null !== $cityIds || null !== $countryIds) {
            $qb
                ->andWhere('p.city IN (:cities) OR p.country IN (:countries)')
                ->setParameter('cities', $cityIds ?? [])
                ->setParameter('countries', $countryIds ?? []);
        }

        return $this->whereUpcoming($qb)->getQuery()->getScalarResult();
    }

    /**
     * Cities ("c") with their number of published events to come ("events"), the busiest first.
     *
     * admin_zone_type_upcoming_idx and admin_zone_type_country_upcoming_idx end with this exact order, so MySQL reads
     * them backwards and stops at the limit. With any other order it sorts instead, from the type index (every city):
     * 90 ms instead of 1 ms on a city page.
     */
    private function createUpcomingCitiesQueryBuilder(int $limit): QueryBuilder
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('c', 'department', 'c.upcomingEvents AS events')
            ->from(City::class, 'c')
            ->leftJoin('c.parent', 'department')
            ->where('c.upcomingEvents > 0')
            ->orderBy('c.upcomingEvents', SortDirection::Descending)
            ->addOrderBy('c.population', SortDirection::Descending)
            ->setMaxResults($limit);
    }

    /**
     * Keeps the published events ("e") that end today or later, in the location when given ("p" is their venue).
     */
    private function whereUpcoming(QueryBuilder $qb, ?Location $location = null): QueryBuilder
    {
        $qb
            ->andWhere('e.endDate >= :from')
            ->andWhere('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->setParameter('from', new DateTimeImmutable()->format('Y-m-d'));

        return $this->whereLocation($qb, $location);
    }

    /**
     * Keeps the venues ("p") of the location when given.
     */
    private function whereLocation(QueryBuilder $qb, ?Location $location): QueryBuilder
    {
        if (true === $location?->isCity()) {
            $qb
                ->andWhere('p.city = :location')
                ->setParameter('location', $location->getCity()->getId());
        } elseif (true === $location?->isCountry()) {
            $qb
                ->andWhere('p.country = :location')
                ->setParameter('location', $location->getCountry()->getId());
        }

        return $qb;
    }
}
