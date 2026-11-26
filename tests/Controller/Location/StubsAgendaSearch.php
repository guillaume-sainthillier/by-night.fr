<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Location;

use App\Entity\City;
use App\Entity\Event;
use App\SearchRepository\CityElasticaRepository;
use App\SearchRepository\EventElasticaRepository;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use FOS\ElasticaBundle\Paginator\PaginatorAdapterInterface;
use FOS\ElasticaBundle\Paginator\PartialResultsInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;

/**
 * The page of a location lists its agenda, which Elasticsearch searches: the tests have no index of their own, so the
 * search finds nothing and counts nothing, or the events and the aggregations given (and as many hits as given, when
 * the count matters more than the events listed). Call it once the client is created, before the
 * request, and only for one request: the client boots a new kernel for the next one.
 */
trait StubsAgendaSearch
{
    /**
     * @param array<string, mixed> $aggregations the "aggregations" of the Elasticsearch response
     * @param list<Event>          $events       the events found, a single page of them
     * @param int|null             $hits         how many events the search counts, when not the events given
     * @param list<City>           $cities       the cities a city search finds
     */
    private function stubAgendaSearch(array $aggregations = [], array $events = [], ?int $hits = null, array $cities = []): void
    {
        $empty = new readonly class($aggregations, $events, $hits ?? \count($events)) implements PaginatorAdapterInterface, PartialResultsInterface {
            /**
             * @param array<string, mixed> $aggregations
             * @param list<Event>          $events
             */
            public function __construct(private array $aggregations, private array $events, private int $hits)
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
                return $this->events;
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

        $cityFinder = $this->createStub(PaginatedFinderInterface::class);
        $cityFinder->method('findPaginated')->willReturnCallback(static fn (): Pagerfanta => new Pagerfanta(new ArrayAdapter($cities)));

        $manager = $this->createStub(RepositoryManagerInterface::class);
        $manager->method('getRepository')->willReturnCallback(static fn (string $class): object => City::class === $class
            ? new CityElasticaRepository($cityFinder)
            : new EventElasticaRepository($finder));

        self::getContainer()->set('fos_elastica.manager.orm', $manager);
    }
}
