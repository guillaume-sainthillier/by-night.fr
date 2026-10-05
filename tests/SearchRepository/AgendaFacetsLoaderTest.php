<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\SearchRepository;

use App\Factory\CityFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Repository\PlaceRepository;
use App\Repository\TagRepository;
use App\Search\SearchEvent;
use App\SearchRepository\AgendaFacetsLoader;
use App\SearchRepository\EventElasticaRepository;
use App\Tests\AppKernelTestCase;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use FOS\ElasticaBundle\Paginator\PaginatorAdapterInterface;

final class AgendaFacetsLoaderTest extends AppKernelTestCase
{
    /**
     * Elasticsearch counts ids: the venues and categories come loaded, in the order of their counts, and an id no
     * longer in the database is left out.
     */
    public function testTheVenuesAndCategoriesOfTheCountsComeLoadedBusiestFirst(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $zenith = PlaceFactory::createOne(['name' => 'Zénith', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $jazz = TagFactory::createOne(['name' => 'Jazz']);
        $rock = TagFactory::createOne(['name' => 'Rock']);

        $facets = $this->createLoader([
            'places' => ['ids' => ['buckets' => [
                ['key' => $zenith->getId(), 'doc_count' => 12],
                ['key' => $bikini->getId(), 'doc_count' => 4],
            ]]],
            'typeCategories' => ['types' => ['buckets' => [
                'concert' => ['categories' => ['buckets' => [
                    ['key' => $rock->getId(), 'doc_count' => 9],
                    ['key' => 999_999, 'doc_count' => 5],
                    ['key' => $jazz->getId(), 'doc_count' => 2],
                ]]],
            ]]],
        ])->load(new SearchEvent(), [], 6, 4);

        self::assertSame([$zenith->getId() => 12, $bikini->getId() => 4], $facets->places);
        self::assertSame([$zenith, $bikini], $facets->venues);
        self::assertSame(['concert' => [['tag' => $rock, 'events' => 9], ['tag' => $jazz, 'events' => 2]]], $facets->categoriesByType);
    }

    public function testNoCountNeedsNoEntity(): void
    {
        $facets = $this->createLoader([])->load(new SearchEvent(), [], 6, 4);

        self::assertSame([], $facets->venues);
        self::assertSame([], $facets->categoriesByType);
    }

    /**
     * @param array<string, mixed> $aggregations
     */
    private function createLoader(array $aggregations): AgendaFacetsLoader
    {
        $adapter = $this->createStub(PaginatorAdapterInterface::class);
        $adapter->method('getAggregations')->willReturn($aggregations);
        $finder = $this->createStub(PaginatedFinderInterface::class);
        $finder->method('createRawPaginatorAdapter')->willReturn($adapter);
        $repositoryManager = $this->createStub(RepositoryManagerInterface::class);
        $repositoryManager->method('getRepository')->willReturn(new EventElasticaRepository($finder));

        return new AgendaFacetsLoader(
            $repositoryManager,
            self::getContainer()->get(PlaceRepository::class),
            self::getContainer()->get(TagRepository::class),
        );
    }
}
