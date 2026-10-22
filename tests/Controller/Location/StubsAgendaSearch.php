<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Location;

use App\SearchRepository\EventElasticaRepository;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use FOS\ElasticaBundle\Paginator\PaginatorAdapterInterface;
use FOS\ElasticaBundle\Paginator\PartialResultsInterface;

/**
 * The page of a location lists its agenda, which Elasticsearch searches: the tests have no index of their own, so the
 * search finds nothing and counts nothing, or the aggregations given (and as many hits as given, none of them listed). Call it once the client is created, before the
 * request, and only for one request: the client boots a new kernel for the next one.
 */
trait StubsAgendaSearch
{
    /**
     * @param array<string, mixed> $aggregations the "aggregations" of the Elasticsearch response
     * @param int                  $hits         how many events the search counts
     */
    private function stubAgendaSearch(array $aggregations = [], int $hits = 0): void
    {
        $empty = new readonly class($aggregations, $hits) implements PaginatorAdapterInterface, PartialResultsInterface {
            /**
             * @param array<string, mixed> $aggregations
             */
            public function __construct(private array $aggregations, private int $hits)
            {
            }

            public function getTotalHits(): int
            {
                return $this->hits;
            }

            public function getResults(int $offset, int $length): PartialResultsInterface
            {
                return $this;
            }

            public function toArray(): array
            {
                return [];
            }

            public function getAggregations(): array
            {
                return $this->aggregations;
            }

            public function getSuggests(): array
            {
                return [];
            }

            public function getMaxScore(): float
            {
                return 0.0;
            }
        };

        $finder = $this->createStub(PaginatedFinderInterface::class);
        $finder->method('createPaginatorAdapter')->willReturn($empty);
        $finder->method('createRawPaginatorAdapter')->willReturn($empty);

        $manager = $this->createStub(RepositoryManagerInterface::class);
        $manager->method('getRepository')->willReturn(new EventElasticaRepository($finder));

        self::getContainer()->set('fos_elastica.manager.orm', $manager);
    }
}
