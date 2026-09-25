<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use App\Api\ApiResource\SearchResult;
use App\Api\Pagination\ArrayPaginator;
use App\Api\Provider\SearchProvider;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\Tag;
use App\Entity\User;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\UserFactory;
use App\SearchRepository\CityElasticaRepository;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\TagElasticaRepository;
use App\SearchRepository\UserElasticaRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Elastica\Result;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\HybridResult;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use FOS\ElasticaBundle\Paginator\PaginatorAdapterInterface;
use FOS\ElasticaBundle\Paginator\PartialResultsInterface;
use FOS\ElasticaBundle\Repository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * GET /api/search lists, on each page, the next few hits of each type: events, cities,
 * members and tags. The types run out at different pages.
 */
final class SearchProviderTest extends AppKernelTestCase
{
    /**
     * 16 per page: 4 hits of each type.
     */
    private const int ITEMS_PER_PAGE = 16;

    public function testAPageCombinesTheHitsOfEachType(): void
    {
        $results = $this->search(page: 1, events: 10, cities: 1);

        self::assertSame(
            ['events', 'events', 'events', 'events', 'cities'],
            array_map(static fn (SearchResult $result): string => $result->type, iterator_to_array($results)),
        );
        self::assertSame(11.0, $results->getTotalItems());
    }

    /**
     * On the dev base, /api/search?q=bikini&page=2 answered 404 while page 1 announced
     * 4,508 hits and a next page: the city results, a single one, had no page 2.
     */
    public function testATypeThatRanOutDoesNotHideTheOthers(): void
    {
        $results = $this->search(page: 2, events: 10, cities: 1);

        self::assertSame(
            ['events', 'events', 'events', 'events'],
            array_map(static fn (SearchResult $result): string => $result->type, iterator_to_array($results)),
        );
    }

    public function testTheLastPageIsTheLastOneWithHits(): void
    {
        // 4 events a page: 10 events take 3 pages
        $results = $this->search(page: 1, events: 10, cities: 1);

        self::assertSame(3.0, $results->getLastPage());
    }

    /**
     * The first and last names are private: a member is shown as on their public profile.
     */
    public function testAMemberIsListedWithoutTheirPrivateName(): void
    {
        $user = UserFactory::createOne(['username' => 'jazzfan', 'firstname' => 'Jeanne', 'lastname' => 'Dupont']);
        $user->setCreatedAt(new DateTimeImmutable('2019-03-14'));

        $results = iterator_to_array($this->createProvider([User::class => [self::hit($user)]])->provide(
            new GetCollection(paginationItemsPerPage: self::ITEMS_PER_PAGE),
            context: ['filters' => ['q' => 'jazzfan']],
        ));

        self::assertSame('Jazzfan', $results[0]->label);
        self::assertSame('Membre depuis 2019', $results[0]->shortDescription);

        $json = json_encode($results, \JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Jeanne', $json);
        self::assertStringNotContainsString('Dupont', $json);
    }

    public function testAnEmptyQueryFindsNothing(): void
    {
        $provider = $this->createProvider([]);

        self::assertSame([], $provider->provide(new GetCollection(paginationItemsPerPage: self::ITEMS_PER_PAGE), context: ['filters' => ['q' => '  ']]));
    }

    /**
     * @return ArrayPaginator<SearchResult>
     */
    private function search(int $page, int $events, int $cities): ArrayPaginator
    {
        $city = CityFactory::createOne();
        $hits = [
            Event::class => array_map(static fn (): HybridResult => self::hit(EventFactory::createOne()), range(1, $events)),
            City::class => array_map(static fn (): HybridResult => self::hit(CityFactory::createOne(['country' => $city->getCountry()])), range(1, $cities)),
            User::class => [],
            Tag::class => [],
        ];

        $results = $this->createProvider($hits)->provide(
            new GetCollection(paginationItemsPerPage: self::ITEMS_PER_PAGE),
            context: ['filters' => ['q' => 'jazz', 'page' => $page]],
        );
        self::assertInstanceOf(ArrayPaginator::class, $results);

        return $results;
    }

    /**
     * @param array<class-string, list<HybridResult>> $hits
     */
    private function createProvider(array $hits): SearchProvider
    {
        $repositories = [
            Event::class => new EventElasticaRepository($this->finder($hits[Event::class] ?? [])),
            City::class => new CityElasticaRepository($this->finder($hits[City::class] ?? [])),
            User::class => new UserElasticaRepository($this->finder($hits[User::class] ?? [])),
            Tag::class => new TagElasticaRepository($this->finder($hits[Tag::class] ?? [])),
        ];

        $repositoryManager = $this->createStub(RepositoryManagerInterface::class);
        $repositoryManager->method('getRepository')->willReturnCallback(static fn (string $indexName): Repository => $repositories[$indexName]);

        return new SearchProvider($repositoryManager, self::getContainer()->get(UrlGeneratorInterface::class), new Pagination());
    }

    private static function hit(object $entity): HybridResult
    {
        return new HybridResult(new Result(['_id' => '1', '_source' => []]), $entity);
    }

    /**
     * @param list<HybridResult> $hits
     */
    private function finder(array $hits): PaginatedFinderInterface
    {
        $adapter = new readonly class($hits) implements PaginatorAdapterInterface {
            /**
             * @param list<HybridResult> $hits
             */
            public function __construct(private array $hits)
            {
            }

            public function getTotalHits(): int
            {
                return \count($this->hits);
            }

            public function getResults(int $offset, int $length): PartialResultsInterface
            {
                return new readonly class(\array_slice($this->hits, $offset, $length), \count($this->hits)) implements PartialResultsInterface {
                    /**
                     * @param list<HybridResult> $hits
                     */
                    public function __construct(private array $hits, private int $totalHits)
                    {
                    }

                    public function toArray(): array
                    {
                        return $this->hits;
                    }

                    public function getTotalHits(): int
                    {
                        return $this->totalHits;
                    }

                    public function getAggregations(): array
                    {
                        return [];
                    }
                };
            }

            public function getAggregations(): array
            {
                return [];
            }

            public function getSuggests(): array
            {
                return [];
            }

            public function getMaxScore(): float
            {
                return 1.0;
            }
        };

        $finder = $this->createStub(PaginatedFinderInterface::class);
        $finder->method('createHybridPaginatorAdapter')->willReturn($adapter);

        return $finder;
    }
}
