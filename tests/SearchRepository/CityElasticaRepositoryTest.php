<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\SearchRepository;

use App\SearchRepository\CityElasticaRepository;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Paginator\PaginatorAdapterInterface;
use Pagerfanta\Adapter\NullAdapter;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\TestCase;

/**
 * The hits are loaded from the database by their _id (FOSElastica's transformer): the autocomplete and the search
 * never read the document itself, the highlights come without it.
 */
final class CityElasticaRepositoryTest extends TestCase
{
    public function testTheHitsComeWithoutTheirDocument(): void
    {
        $queries = [];
        $finder = $this->createStub(PaginatedFinderInterface::class);
        $finder->method('findPaginated')->willReturnCallback(static function (Query $query) use (&$queries): Pagerfanta {
            $queries[] = $query->toArray();

            return new Pagerfanta(new NullAdapter());
        });
        $finder->method('createHybridPaginatorAdapter')->willReturnCallback(function (Query $query) use (&$queries): PaginatorAdapterInterface {
            $queries[] = $query->toArray();

            return $this->createStub(PaginatorAdapterInterface::class);
        });

        $repository = new CityElasticaRepository($finder);
        $repository->findWithSearch('toulouse');
        $repository->findWithHighlightsPaginated('toulouse');

        [$autocomplete, $highlighted] = $queries;
        self::assertFalse($autocomplete['_source']);
        self::assertFalse($highlighted['_source']);
        self::assertSame(['name', 'country.name'], array_keys($highlighted['highlight']['fields']));
    }
}
