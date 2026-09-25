<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\SearchRepository;

use App\Search\SearchEvent;
use App\SearchRepository\EventElasticaRepository;
use DateTimeImmutable;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use PHPUnit\Framework\TestCase;

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
    public function testATypePageAlsoListsTheEventsNamingAnyOfItsTerms(): void
    {
        $search = new SearchEvent()->setTypeTerms('soirée, boîte de nuit');

        $must = $this->repository->createSearchQuery($search)->toArray()['query']['bool']['must'];

        self::assertCount(1, $must);
        $anyTerm = $must[0]['bool'];
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
        self::assertContains('soirée, boîte de nuit', array_map(static fn (array $clause) => $clause['multi_match']['query'] ?? null, $anyTerm['should']), 'What the keywords query found stays listed');
    }

    public function testKeywordsTypedOnATypePageAreSearchedAsKeywordsOnly(): void
    {
        $search = new SearchEvent()->setTypeTerms('concert, musique, artiste')->setTerm('jazz');

        $must = $this->repository->createSearchQuery($search)->toArray()['query']['bool']['must'];

        self::assertSame([], $search->getTypeTerms());
        self::assertSame('jazz', $must[0]['multi_match']['query']);
    }

    public function testTheCategoryFilterReachesTheThemes(): void
    {
        $search = new SearchEvent()->setType(['Concert']);

        $filters = $this->repository->createSearchQuery($search)->toArray()['query']['bool']['filter'];

        $typeFilter = end($filters)['bool'];
        self::assertSame(1, $typeFilter['minimum_should_match']);
        self::assertContains(['multi_match' => ['query' => 'Concert', 'fields' => ['type', 'category.name']]], $typeFilter['should']);
        self::assertContains(
            ['nested' => ['path' => 'themes', 'query' => ['match_phrase' => ['themes.name' => 'Concert']]]],
            $typeFilter['should'],
            'Themes are nested documents: only a nested query reaches them',
        );
    }
}
