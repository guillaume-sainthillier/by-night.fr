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
 * What a member's distinctions are earned from (MemberDistinction::earnedBy()): when and where they go out, and what
 * they give to the site. The counts are about the published events of their calendar, unless said otherwise.
 */
final readonly class MemberStats
{
    public function __construct(
        public MemberActivity $activity,
        /** The events the member published themselves */
        public int $publishedEvents = 0,
        /** The member's approved comments */
        public int $comments = 0,
        /** The cities of the venues */
        public int $cities = 0,
        /** The events at the member's busiest venue */
        public int $busiestPlaceEvents = 0,
        /** The events added to the calendar long before they started (EventRepository::countUserCalendarHabits()) */
        public int $eventsAddedAhead = 0,
        /** The free events */
        public int $freeEvents = 0,
    ) {
    }
}
