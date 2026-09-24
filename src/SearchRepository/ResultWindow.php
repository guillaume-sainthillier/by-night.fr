<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

/**
 * Elasticsearch refuses a search whose from + size goes beyond the index.max_result_window setting
 * (10 000, left at its default on these indexes), whatever the number of hits: a page past it
 * must not be queried.
 */
final class ResultWindow
{
    public const int MAX_RESULTS = 10_000;

    /**
     * @return positive-int
     */
    public static function getMaxPages(int $perPage): int
    {
        return max(1, intdiv(self::MAX_RESULTS, max(1, $perPage)));
    }

    public static function contains(int $page, int $perPage): bool
    {
        return $page * $perPage <= self::MAX_RESULTS;
    }
}
