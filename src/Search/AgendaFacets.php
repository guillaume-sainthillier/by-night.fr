<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Search;

/**
 * The counts next to the agenda filters, from Elasticsearch aggregations (EventElasticaRepository::getFacets()).
 */
final readonly class AgendaFacets
{
    /**
     * @param array<string, int>             $dates          the events of each date window, by the name it was asked with (a
     *                                                       DateRangePreset value, a day as Y-m-d)
     * @param array<string, int>             $types          the events of each type page, by AgendaType value, and of the whole agenda ("all")
     * @param array<int, int>                $places         the events of the busiest venues by place id, the busiest first
     * @param array<string, array<int, int>> $typeCategories the events of the busiest categories of each type by tag
     *                                                       id, the busiest first
     */
    public function __construct(
        public array $dates = [],
        public array $types = [],
        public array $places = [],
        public array $typeCategories = [],
    ) {
    }

    /**
     * @param array<string, mixed> $aggregations the "aggregations" of the Elasticsearch response
     */
    public static function fromAggregations(array $aggregations): self
    {
        $places = [];
        foreach ($aggregations['places']['ids']['buckets'] ?? [] as $bucket) {
            $places[(int) $bucket['key']] = (int) $bucket['doc_count'];
        }

        $typeCategories = [];
        foreach ($aggregations['typeCategories']['types']['buckets'] ?? [] as $type => $bucket) {
            foreach ($bucket['categories']['buckets'] ?? [] as $category) {
                $typeCategories[$type][(int) $category['key']] = (int) $category['doc_count'];
            }
        }

        return new self(
            self::countsOf($aggregations['dates']['windows']['buckets'] ?? []),
            self::countsOf($aggregations['types']['types']['buckets'] ?? []),
            $places,
            $typeCategories,
        );
    }

    /**
     * @param array<string, array{doc_count: int}> $buckets the named buckets of a "filters" aggregation
     *
     * @return array<string, int>
     */
    private static function countsOf(array $buckets): array
    {
        return array_map(static fn (array $bucket): int => (int) $bucket['doc_count'], $buckets);
    }
}
