<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import\CrossSource;

use DateTimeImmutable;

/**
 * Two events of two sources whose titles look alike at the same venue, and what the matcher made of them.
 */
final readonly class CrossSourcePair
{
    public function __construct(
        public int $leftId,
        public string $leftSource,
        public string $leftName,
        public int $rightId,
        public string $rightSource,
        public string $rightName,
        public string $placeName,
        public ?string $cityName,
        public ?DateTimeImmutable $startDate,
        public MatchVerdict $verdict,
    ) {
    }
}
