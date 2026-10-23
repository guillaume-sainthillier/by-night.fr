<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Stats;

/**
 * What a publisher's page shows and its distinctions are earned from (PublisherDistinction::earnedBy()). The counts
 * are about its events online: published, and not folded into another one's page.
 */
final readonly class PublisherStats
{
    public function __construct(
        public int $events = 0,
        /** Those not over yet */
        public int $upcomingEvents = 0,
        public int $freeEvents = 0,
        /** The "J'y vais" of its events */
        public int $participations = 0,
        /** The favourites of its events */
        public int $interests = 0,
        /** The cities of the venues */
        public int $cities = 0,
        /** The events at its busiest venue */
        public int $busiestPlaceEvents = 0,
        /** The year of its first event */
        public ?int $firstYear = null,
        /** The months of the last year it had an event in */
        public int $activeMonths = 0,
    ) {
    }

    /**
     * The members its events reach: their "J'y vais" and favourites.
     */
    public function getAudience(): int
    {
        return $this->participations + $this->interests;
    }
}
