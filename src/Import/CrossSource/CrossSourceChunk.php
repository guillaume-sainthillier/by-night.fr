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
     * @param int                     $placeCount     the venues scanned
     * @param array<int, int>         $eventPlaces    the venue of every imported event of these venues that is not over,
     *                                                by event id: the links between two of them are the ones this scan
     *                                                decides on, as those leading from one of them to another venue
     * @param list<CrossSourcePair>   $pairs          the pairs whose titles agree, with the verdict of the full match
     * @param array<int, string|null> $identityHashes the identity hash of each of these events, by event id
     */
    public function __construct(
        public int $placeCount,
        public array $eventPlaces,
        public array $pairs,
        public array $identityHashes = [],
    ) {
    }
}
