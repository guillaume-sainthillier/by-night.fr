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
 * The events the matched pairs add up to around one show.
 */
final readonly class CrossSourceGroup
{
    /**
     * @param array<int, array{source: string, name: string}> $members   by event id
     * @param list<array{int, int}>                           $conflicts the members whose titles name two shows
     */
    public function __construct(
        public array $members,
        public array $conflicts,
    ) {
    }

    /**
     * Every two members name the same show, not only the pairs matched: a title naming the artist alone matches
     * each of their shows ("Chantal Ladesou" both "Chantal Ladesou - Iconique" and "Chantal Ladesou - Forever"),
     * and would chain them into one.
     */
    public function isConsistent(): bool
    {
        return [] === $this->conflicts;
    }

    public function hasSeveralEventsOfOneSource(): bool
    {
        $sources = array_column($this->members, 'source');

        return \count(array_unique($sources)) < \count($sources);
    }
}
