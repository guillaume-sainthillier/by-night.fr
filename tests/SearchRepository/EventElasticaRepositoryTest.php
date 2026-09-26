<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\SearchRepository;

use App\Enum\AgendaType;
use App\Search\DateRange;
use App\Search\SearchEvent;
use App\SearchRepository\EventElasticaRepository;
use DateTimeImmutable;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The agenda query is built per session: an event is listed when one of its sessions
 * overlaps the requested window, and sorted by the soonest-ending session in it.
 */
final class EventElasticaRepositoryTest extends TestCase
{
    private EventElasticaRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new EventElasticaRepository($this->createStub(PaginatedFinderInterface::class));
    }

    public function testEventsAreFilteredAndSortedBySessionsOverlappingTheWindow(): void
    {
        $search = new SearchEvent()
            ->setFrom(new DateTimeImmutable('2026-10-10'))
            ->setTo(new DateTimeImmutable('2026-10-12'));

        $query = $this->repository->createSearchQuery($search)->toArray();

        $overlapping = ['bool' => ['filter' => [
            ['range' => ['sessions.endAt' => ['gte' => '2026-10-10']]],
            ['range' => ['sessions.startAt' => ['lte' => '2026-10-12']]],
        ]]];

        self::assertEquals(
            [['nested' => ['path' => 'sessions', 'query' => $overlapping]]],
            $query['query']['bool']['filter'],
            'A session, not the overall range, must overlap the window.',
        );
        self::assertEquals(
            [['sessions.endAt' => ['order' => 'asc', 'mode' => 'min', 'nested' => ['path' => 'sessions', 'filter' => $overlapping]]]],
            $query['sort'],
            'Sorted by the soonest-ending session inside the window.',
        );
    }

    public function testAnOpenEndedSearchOnlyRequiresASessionStillToCome(): void
    {
        $search = new SearchEvent()->setFrom(new DateTimeImmutable('2026-10-10'));

        $query = $this->repository->createSearchQuery($search)->toArray();

        $stillToCome = ['bool' => ['filter' => [
            ['range' => ['sessions.endAt' => ['gte' => '2026-10-10']]],
        ]]];

        self::assertEquals([['nested' => ['path' => 'sessions', 'query' => $stillToCome]]], $query['query']['bool']['filter']);
        self::assertEquals(
            [['sessions.endAt' => ['order' => 'asc', 'mode' => 'min', 'nested' => ['path' => 'sessions', 'filter' => $stillToCome]]]],
            $query['sort'],
        );
    }

    /**
     * Elasticsearch stops counting at 10 000 hits by default: the agenda of France read "sur 10 000" events.
     */
    public function testTheAgendaCountsAllItsEvents(): void
    {
        self::assertTrue($this->repository->createSearchQuery(new SearchEvent())->toArray()['track_total_hits']);
        self::assertTrue($this->repository->createSearchQuery(new SearchEvent()->setTerm('jazz'))->toArray()['track_total_hits']);
    }

    public function testATermSearchIsSortedByRelevance(): void
    {
        $search = new SearchEvent()->setTerm('jazz');

        $query = $this->repository->createSearchQuery($search)->toArray();

        self::assertArrayNotHasKey('sort', $query);
        self::assertArrayHasKey('must', $query['query']['bool']);
    }

    /**
     * All the synonyms of a type page together matched almost nothing ("étudiant": 0 of
     * 4,266 upcoming events in Toulouse): an event naming any one of them is listed too,
     * on top of what the keywords query finds.
     */
    public function testATypePageListsTheEventsNamingAnyOfItsTerms(): void
    {
        $anyTerm = $this->typeFilterOf(AgendaType::Student)['bool'];

        self::assertSame(1, $anyTerm['minimum_should_match']);
        self::assertContains(['multi_match' => [
            'query' => 'boîte de nuit',
            'type' => 'phrase',
            'fields' => ['name^5', 'name.heavy^5', 'category.name^3', 'type'],
        ]], $anyTerm['should']);
        self::assertContains(
            ['nested' => ['path' => 'themes', 'query' => ['match_phrase' => ['themes.name' => 'soirée']]]],
            $anyTerm['should'],
        );
        self::assertContains('soirée étudiant bar discothèque boîte de nuit after work', array_map(static fn (array $clause) => $clause['multi_match']['query'] ?? null, $anyTerm['should']), 'What the keywords query found stays listed');
    }

    /**
     * The type is a filter, which scores nothing: its page is sorted by date, so its days
     * follow each other (AgendaSection), where relevance scattered them.
     */
    public function testATypePageIsSortedByDate(): void
    {
        $query = $this->repository->createSearchQuery(new SearchEvent()->setType(AgendaType::Concert))->toArray();

        self::assertArrayNotHasKey('must', $query['query']['bool']);
        self::assertArrayHasKey('sessions.endAt', $query['sort'][0]);
    }

    public function testKeywordsTypedOnATypePageNarrowItDown(): void
    {
        $search = new SearchEvent()->setType(AgendaType::Concert)->setTerm('jazz');

        $bool = $this->repository->createSearchQuery($search)->toArray()['query']['bool'];

        self::assertSame('jazz', $bool['must'][0]['multi_match']['query']);
        self::assertEquals($this->typeFilterOf(AgendaType::Concert), $bool['filter'][1], 'The keywords used to replace the type');
    }

    public function testTheDateCountsKeepTheFiltersOfThePageButItsDates(): void
    {
        $search = new SearchEvent()
            ->setFrom(new DateTimeImmutable('2026-10-10'))
            ->setTo(new DateTimeImmutable('2026-10-12'))
            ->setLieux([12])
            ->setType(AgendaType::Concert)
            ->setTagId(7)
            ->setTerm('jazz');

        $dates = $this->repository->createFacetsQuery($search, [
            'today' => new DateRange(new DateTimeImmutable('2026-10-10'), new DateTimeImmutable('2026-10-10')),
            'anytime' => new DateRange(new DateTimeImmutable('2026-10-10')),
        ], 6)->toArray()['aggs']['dates'];

        $filters = $dates['filter']['bool']['filter'];
        self::assertContains(['terms' => ['place.id' => [12]]], $filters, 'A venue page counts its own dates');
        self::assertEquals($this->typeFilterOf(AgendaType::Concert), $filters[1]);
        self::assertSame('jazz', $filters[2]['multi_match']['query']);
        self::assertSame(7, $filters[3]['bool']['should'][0]['term']['category.id']);
        self::assertCount(4, $filters, 'The window of the page is left out: each date is its own window');

        self::assertEquals(
            ['nested' => ['path' => 'sessions', 'query' => ['bool' => ['filter' => [
                ['range' => ['sessions.endAt' => ['gte' => '2026-10-10']]],
                ['range' => ['sessions.startAt' => ['lte' => '2026-10-10']]],
            ]]]]],
            $dates['aggs']['windows']['filters']['filters']['today'],
        );
        self::assertEquals(
            ['nested' => ['path' => 'sessions', 'query' => ['bool' => ['filter' => [
                ['range' => ['sessions.endAt' => ['gte' => '2026-10-10']]],
            ]]]]],
            $dates['aggs']['windows']['filters']['filters']['anytime'],
        );
    }

    /**
     * A type link keeps the dates, the venue, the category and the keywords of the page, but
     * not its type, which it replaces. It counts the events its page's type filter found last
     * night (Event::$agendaTypes), not that full-text search again.
     */
    public function testTheTypeCountsKeepTheDatesTheVenueTheCategoryAndTheKeywords(): void
    {
        $search = new SearchEvent()
            ->setFrom(new DateTimeImmutable('2026-10-10'))
            ->setLieux([12])
            ->setType(AgendaType::Show)
            ->setTagId(7)
            ->setTerm('jazz');

        $types = $this->repository->createFacetsQuery($search, [], 6)->toArray()['aggs']['types'];

        $filters = $types['filter']['bool']['filter'];
        self::assertCount(4, $filters);
        self::assertEquals(['nested' => ['path' => 'sessions', 'query' => ['bool' => ['filter' => [
            ['range' => ['sessions.endAt' => ['gte' => '2026-10-10']]],
        ]]]]], $filters[0]);
        self::assertEquals(['terms' => ['place.id' => [12]]], $filters[1]);
        self::assertSame('jazz', $filters[2]['multi_match']['query']);
        self::assertEquals(['term' => ['category.id' => 7]], $filters[3]['bool']['should'][0]);

        $counts = $types['aggs']['types']['filters']['filters'];
        self::assertSame(['all', 'concert', 'show', 'exhibition', 'family', 'student'], array_keys($counts));
        self::assertEquals(['match_all' => new stdClass()], $counts['all']);
        self::assertEquals(['term' => ['agendaTypes' => 'concert']], $counts['concert']);
        self::assertArrayNotHasKey('typeCategories', $this->repository->createFacetsQuery($search, [], 6)->toArray()['aggs'], 'No categories asked');
    }

    /**
     * The busiest categories of each type keep the filters of the type but the category, which
     * their links replace.
     */
    public function testEachTypeCountsItsBusiestCategoriesWithoutTheCategoryOfThePage(): void
    {
        $search = new SearchEvent()
            ->setFrom(new DateTimeImmutable('2026-10-10'))
            ->setLieux([12])
            ->setTagId(7)
            ->setTerm('jazz');

        $categories = $this->repository->createFacetsQuery($search, [], 6, 4)->toArray()['aggs']['typeCategories'];

        $filters = $categories['filter']['bool']['filter'];
        self::assertCount(3, $filters, 'The category of the page is left out');
        self::assertEquals(['terms' => ['place.id' => [12]]], $filters[1]);
        self::assertSame('jazz', $filters[2]['multi_match']['query']);
        self::assertSame(['concert', 'show', 'exhibition', 'family', 'student'], array_keys($categories['aggs']['types']['filters']['filters']));
        self::assertSame(['terms' => ['field' => 'category.id', 'size' => 4]], $categories['aggs']['types']['aggs']['categories']);
    }

    public function testTheCountsLeaveOutTheEventsOverBeforeTheEarliestWindow(): void
    {
        $search = new SearchEvent()->setFrom(new DateTimeImmutable('2026-10-12'));
        $dates = [
            'anytime' => new DateRange(new DateTimeImmutable('2026-10-10')),
            'tomorrow' => new DateRange(new DateTimeImmutable('2026-10-11'), new DateTimeImmutable('2026-10-11')),
        ];

        $query = $this->repository->createFacetsQuery($search, $dates, 6)->toArray();

        self::assertEquals([['nested' => ['path' => 'sessions', 'query' => ['bool' => ['filter' => [
            ['range' => ['sessions.endAt' => ['gte' => '2026-10-10']]],
        ]]]]]], $query['query']['bool']['filter']);
    }

    public function testTheTypesOfTheEventsToComeAreFoundWithTheFilterOfTheirPage(): void
    {
        $query = $this->repository->createAgendaTypeQuery(AgendaType::Concert, new DateTimeImmutable('2026-10-10'))->toArray();

        self::assertEquals([
            ['nested' => ['path' => 'sessions', 'query' => ['bool' => ['filter' => [
                ['range' => ['sessions.endAt' => ['gte' => '2026-10-10']]],
            ]]]]],
            $this->typeFilterOf(AgendaType::Concert),
        ], $query['query']['bool']['filter']);
        self::assertFalse($query['_source']);
    }

    /**
     * A venue link keeps the dates, the type, the category and the keywords; the venues counted
     * are those around, not only the venue of the page.
     */
    public function testTheVenueCountsKeepTheDatesTheTypeTheCategoryAndTheKeywords(): void
    {
        $search = new SearchEvent()
            ->setFrom(new DateTimeImmutable('2026-10-10'))
            ->setLieux([12])
            ->setType(AgendaType::Concert)
            ->setTagId(7)
            ->setTerm('jazz');

        $places = $this->repository->createFacetsQuery($search, [], 6)->toArray()['aggs']['places'];

        $filters = $places['filter']['bool']['filter'];
        self::assertCount(4, $filters, 'The venue of the page is left out');
        self::assertEquals($this->typeFilterOf(AgendaType::Concert), $filters[1]);
        self::assertSame('jazz', $filters[2]['multi_match']['query']);
        self::assertEquals(['term' => ['category.id' => 7]], $filters[3]['bool']['should'][0]);
        self::assertSame(['field' => 'place.id', 'size' => 6], $places['aggs']['ids']['terms']);
    }

    public function testCountsWithoutFiltersMatchAll(): void
    {
        $query = $this->repository->createFacetsQuery(new SearchEvent(), ['anytime' => new DateRange(new DateTimeImmutable('2026-10-10'))], 6)->toArray();

        // The date counts keep no window of the page: an empty bool query would be sent as "bool": [], which
        // Elasticsearch rejects
        self::assertEquals(['match_all' => new stdClass()], $query['aggs']['dates']['filter']);
        self::assertSame(0, $query['size']);
    }

    /**
     * The clause the page of a type filters its events with.
     *
     * @return array<string, mixed>
     */
    private function typeFilterOf(AgendaType $type): array
    {
        // After the window of the dates, which every search has
        return $this->repository->createSearchQuery(new SearchEvent()->setType($type))->toArray()['query']['bool']['filter'][1];
    }
}
