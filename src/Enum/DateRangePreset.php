<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

use App\Search\DateRange;
use DateTimeImmutable;

/**
 * The date shortcuts of the agenda, which its URLs name ("?when=this_weekend"): a link stays true the next week.
 */
enum DateRangePreset: string
{
    case Anytime = 'anytime';
    case Today = 'today';
    case Tomorrow = 'tomorrow';
    case ThisWeekend = 'this_weekend';
    case ThisWeek = 'this_week';
    case ThisMonth = 'this_month';

    public function getLabel(): string
    {
        return match ($this) {
            self::Anytime => 'Tous les jours',
            self::Today => "Aujourd'hui",
            self::Tomorrow => 'Demain',
            self::ThisWeekend => 'Ce week-end',
            self::ThisWeek => 'Cette semaine',
            self::ThisMonth => 'Ce mois',
        };
    }

    /**
     * The days of the shortcut from today on: "Cette semaine" starts on Monday, but the days already gone have nothing
     * left to show.
     *
     * @param DateTimeImmutable|null $today the day the shortcut is read on: today by default, the day of a weekly e-mail
     */
    public function range(?DateTimeImmutable $today = null): DateRange
    {
        $today = ($today ?? new DateTimeImmutable())->setTime(0, 0);
        [$from, $to] = match ($this) {
            self::Anytime => [$today, null],
            self::Today => [$today, $today],
            self::Tomorrow => [$today->modify('+1 day'), $today->modify('+1 day')],
            self::ThisWeekend => [$today->modify('friday this week'), $today->modify('sunday this week')],
            self::ThisWeek => [$today->modify('monday this week'), $today->modify('sunday this week')],
            self::ThisMonth => [$today->modify('first day of this month'), $today->modify('last day of this month')],
        };

        return new DateRange(max($from, $today), $to);
    }
}
