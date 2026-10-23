<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Analytics;

use App\Enum\AudiencePeriod;
use App\Search\DateRange;
use DateTimeImmutable;

/**
 * The days an organiser asks to see the statistics of: a shortcut ("?period="), or dates picked ("?dateRange[from]="),
 * which win, as on the agenda (SearchEvent).
 */
final class AudienceQuery
{
    /** The longest period a chart shows, a point a day */
    public const int MAX_DAYS = 366;

    public ?AudiencePeriod $period = null;

    public ?DateTimeImmutable $from = null;

    public ?DateTimeImmutable $to = null;

    /**
     * The shortcut the page is on: the one asked, else the last 30 days; none for dates picked.
     */
    public function getPreset(): ?AudiencePeriod
    {
        return null === $this->from ? $this->period ?? AudiencePeriod::Last30Days : null;
    }

    /**
     * The days shown: those picked, put in order, never after today nor longer than MAX_DAYS (the most recent kept),
     * else those of the shortcut.
     */
    public function getDateRange(): DateRange
    {
        if (null === $this->from) {
            return ($this->period ?? AudiencePeriod::Last30Days)->range();
        }

        $today = new DateTimeImmutable('today');
        $from = min($this->from, $today);
        $to = min($this->to ?? $today, $today);
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $earliest = $to->modify(\sprintf('-%d days', self::MAX_DAYS - 1));

        return new DateRange(max($from, $earliest), $to);
    }
}
