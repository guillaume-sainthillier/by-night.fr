<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Search;

use App\Search\AgendaFacets;
use PHPUnit\Framework\TestCase;

final class AgendaFacetsTest extends TestCase
{
    public function testTheCountsAreReadFromTheAggregations(): void
    {
        $facets = AgendaFacets::fromAggregations([
            'dates' => ['doc_count' => 4266, 'windows' => ['buckets' => [
                'today' => ['doc_count' => 354],
                '2026-09-26' => ['doc_count' => 412],
            ]]],
            'types' => ['doc_count' => 4266, 'types' => ['buckets' => [
                'all' => ['doc_count' => 4266],
                'concert' => ['doc_count' => 255],
            ]]],
            'places' => ['doc_count' => 4266, 'ids' => ['buckets' => [
                ['key' => 118, 'doc_count' => 340],
                ['key' => 27, 'doc_count' => 319],
            ]]],
        ]);

        self::assertSame(['today' => 354, '2026-09-26' => 412], $facets->dates);
        self::assertSame(['all' => 4266, 'concert' => 255], $facets->types);
        self::assertSame([118 => 340, 27 => 319], $facets->places, 'The busiest venues first, by place id');
        self::assertSame([], $facets->typeCategories, 'None asked');
    }

    public function testTheCategoriesOfEachTypeAreReadFromItsBucket(): void
    {
        $facets = AgendaFacets::fromAggregations([
            'typeCategories' => ['doc_count' => 4266, 'types' => ['buckets' => [
                'concert' => ['doc_count' => 255, 'categories' => ['buckets' => [
                    ['key' => 60, 'doc_count' => 16],
                    ['key' => 9, 'doc_count' => 12],
                ]]],
                'student' => ['doc_count' => 0, 'categories' => ['buckets' => []]],
            ]]],
        ]);

        self::assertSame(['concert' => [60 => 16, 9 => 12]], $facets->typeCategories);
    }

    public function testThePriceCountsAreReadFromTheirBuckets(): void
    {
        $facets = AgendaFacets::fromAggregations([
            'prices' => ['doc_count' => 4266, 'prices' => ['buckets' => [
                'any' => ['doc_count' => 4266],
                'free' => ['doc_count' => 312],
                'under_20' => ['doc_count' => 1240],
            ]]],
        ]);

        self::assertSame(['any' => 4266, 'free' => 312, 'under_20' => 1240], $facets->prices);
    }

    public function testNoAggregationsCountNothing(): void
    {
        $facets = AgendaFacets::fromAggregations([]);

        self::assertSame([], $facets->dates);
        self::assertSame([], $facets->types);
        self::assertSame([], $facets->places);
    }
}
