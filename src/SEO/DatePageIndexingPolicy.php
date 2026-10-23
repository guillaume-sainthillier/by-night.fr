<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SEO;

use App\App\Location;

/**
 * Decides which date pages ("/toulouse/ce-week-end") search engines may index, are linked to and submitted in the
 * sitemap.
 *
 * Every location has them, but tens of thousands of cities have a handful of events a month: their weekend would be an
 * empty or near-empty page, thin content repeated by the thousand. Only the busy locations get theirs indexed, judged
 * on the nightly count of their events to come (UpcomingEventCounter) rather than on the page's own results: a page
 * indexed one week and not the next is dropped by Google and has to be found again.
 *
 * Measured in production on 2026-10-22: of the 202 cities with 100 events to come or more, 161 had 10 events or more
 * that weekend (43 on average); of the 234 with 50 to 99, 83 (9 on average).
 */
final readonly class DatePageIndexingPolicy
{
    /** The events to come a location needs before its date pages are indexed */
    public const int MIN_UPCOMING_EVENTS = 100;

    public function isIndexable(Location $location): bool
    {
        $zone = $location->getCity() ?? $location->getCountry();

        return null !== $zone && $this->isBusy($zone->getUpcomingEvents());
    }

    /**
     * @param int $upcomingEvents the location's events to come, as UpcomingEventCounter last stored them
     */
    public function isBusy(int $upcomingEvents): bool
    {
        return $upcomingEvents >= self::MIN_UPCOMING_EVENTS;
    }
}
