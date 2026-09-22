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
}
