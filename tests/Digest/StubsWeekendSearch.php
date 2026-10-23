<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Digest;

use App\Digest\WeekendDigestSelector;
use App\Entity\Event;
use App\Repository\EventRepository;
use App\SearchRepository\EventElasticaRepository;
use Elastica\Index;
use Elastica\Query;
use Elastica\Response;
use Elastica\Result;
use Elastica\ResultSet;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;

/**
 * The tests have no Elasticsearch index, and the kernel's is in use as soon as an event is created: a selector whose
 * weekend search finds the events given, in their order.
 */
trait StubsWeekendSearch
{
    /**
     * @param list<Event> $found
     */
    private function weekendSelector(array $found): WeekendDigestSelector
    {
        $results = array_map(static fn (Event $event): Result => new Result(['_id' => (string) $event->getId()]), $found);
        $index = $this->createStub(Index::class);
        $index->method('search')->willReturn(new ResultSet(new Response([]), new Query(), $results));

        // Only builds the query, which reads nothing from Elasticsearch
        $manager = $this->createStub(RepositoryManagerInterface::class);
        $manager->method('getRepository')->willReturn(new EventElasticaRepository($this->createStub(PaginatedFinderInterface::class)));

        return new WeekendDigestSelector($index, $manager, self::getContainer()->get(EventRepository::class));
    }
}
