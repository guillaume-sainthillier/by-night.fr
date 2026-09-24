<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Api;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\State\Pagination\Pagination;
use App\Api\ApiResource\CityAutocomplete;
use App\Api\ApiResource\SearchResult;
use App\Entity\Tag;
use App\Tests\AppKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Clients choose the page size of the public searches, up to a bound: each item is an entity loaded
 * from the database after an Elasticsearch query.
 */
final class PaginationLimitTest extends AppKernelTestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideSearchResources(): iterable
    {
        yield 'search' => [SearchResult::class];
        yield 'city autocomplete' => [CityAutocomplete::class];
        yield 'tags' => [Tag::class];
    }

    /**
     * @param class-string $resourceClass
     */
    #[DataProvider('provideSearchResources')]
    public function testTheClientPageSizeIsBounded(string $resourceClass): void
    {
        $operation = self::getContainer()->get(ResourceMetadataCollectionFactoryInterface::class)
            ->create($resourceClass)
            ->getOperation(forceCollection: true);

        $limit = self::getContainer()->get(Pagination::class)->getLimit($operation, ['filters' => ['itemsPerPage' => 9000]]);

        self::assertSame(50, $limit);
    }
}
