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
 * What CrossSourceLinker did, or would do, at a few venues.
 */
final readonly class CrossSourceLinkResult
{
    /**
     * @param list<CrossSourceGroup> $groups  the shows the matched pairs add up to
     * @param int                    $added   links made (or to make)
     * @param int                    $removed links taken back (or to take back)
     */
    public function __construct(
        public CrossSourceChunk $chunk,
        public array $groups,
        public int $added,
        public int $removed,
    ) {
    }
}
