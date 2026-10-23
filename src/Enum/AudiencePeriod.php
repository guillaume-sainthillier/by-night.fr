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
 * The date shortcuts of an event's statistics, looking back from today, which their URLs name ("?period=last_90_days"),
 * as the agenda's DateRangePreset look ahead.
 */
enum AudiencePeriod: string
{
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case Last90Days = 'last_90_days';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';

    public function getLabel(): string
    {
        return match ($this) {
            self::Last7Days => '7 derniers jours',
            self::Last30Days => '30 derniers jours',
            self::Last90Days => '90 derniers jours',
            self::ThisMonth => 'Ce mois',
            self::LastMonth => 'Le mois dernier',
        };
    }

    public function range(): DateRange
    {
        $today = new DateTimeImmutable('today');

        return match ($this) {
            self::Last7Days => new DateRange($today->modify('-6 days'), $today),
            self::Last30Days => new DateRange($today->modify('-29 days'), $today),
            self::Last90Days => new DateRange($today->modify('-89 days'), $today),
            self::ThisMonth => new DateRange($today->modify('first day of this month'), $today),
            self::LastMonth => new DateRange($today->modify('first day of last month'), $today->modify('last day of last month')),
        };
    }
}
