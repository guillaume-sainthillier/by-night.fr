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
use App\SearchRepository\CityElasticaRepository;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\Fuzzy;
use App\SearchRepository\TagElasticaRepository;
use App\SearchRepository\UserElasticaRepository;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Paginator\PaginatorAdapterInterface;
use Pagerfanta\Adapter\NullAdapter;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\TestCase;

/**
 * Every full-text search forgives typos but on the first letter (Fuzzy): a fuzzy clause without that prefix expands
 * each word over the whole index.
 */
final class FuzzyTest extends TestCase
{
    public function testNoSearchForgivesATypoOnTheFirstLetter(): void
    {
        $queries = [];
        $finder = $this->createStub(PaginatedFinderInterface::class);
        $capture = function (Query $query) use (&$queries): PaginatorAdapterInterface {
            $queries[] = $query->toArray();

            return $this->createStub(PaginatorAdapterInterface::class);
        };
        $finder->method('createPaginatorAdapter')->willReturnCallback($capture);
        $finder->method('createHybridPaginatorAdapter')->willReturnCallback($capture);
        $finder->method('findPaginated')->willReturnCallback(static function (Query $query) use (&$queries): Pagerfanta {
            $queries[] = $query->toArray();

            return new Pagerfanta(new NullAdapter());
        });

        $events = new EventElasticaRepository($finder);
        $queries[] = $events->createSearchQuery(new SearchEvent()->setTerm('concert'))->toArray();
        $events->findWithHighlightsPaginated('concert');
        foreach ([new CityElasticaRepository($finder), new TagElasticaRepository($finder), new UserElasticaRepository($finder)] as $repository) {
            $repository->findWithSearch('toulouse');
            $repository->findWithHighlightsPaginated('toulouse');
        }

        $clauses = self::fuzzyClausesOf($queries);

        // The events' fields and themes on the search page, the autocomplete, and two queries of each other index
        self::assertCount(9, $clauses);
        foreach ($clauses as $clause) {
            self::assertSame(Fuzzy::PREFIX_LENGTH, $clause['prefix_length'] ?? null, (string) json_encode($clause));
        }
    }

    /**
     * @param array<mixed> $query
     *
     * @return list<array<mixed>>
     */
    private static function fuzzyClausesOf(array $query): array
    {
        $clauses = isset($query['fuzziness']) ? [$query] : [];
        foreach ($query as $value) {
            if (\is_array($value)) {
                $clauses = [...$clauses, ...self::fuzzyClausesOf($value)];
            }
        }

        return $clauses;
    }
}
