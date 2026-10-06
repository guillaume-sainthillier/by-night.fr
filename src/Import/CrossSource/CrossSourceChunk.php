<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import\CrossSource;

/**
 * What CrossSourceDuplicateFinder found at a few venues.
 */
final readonly class CrossSourceChunk
{
    /**
     * @param int                   $placeCount the venues scanned
     * @param list<int>             $eventIds   every imported event of these venues that is not over: the links
     *                                          between two of them are the ones this scan decides on
     * @param list<CrossSourcePair> $pairs      the pairs whose titles agree, with the verdict of the full match
     */
    public function __construct(
        public int $placeCount,
        public array $eventIds,
        public array $pairs,
    ) {
    }
}
