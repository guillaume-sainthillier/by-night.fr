<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Enum;

use App\Enum\DateRangePreset;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DateRangePresetTest extends TestCase
{
    /**
     * "Cette semaine" starts on Monday and "Ce mois" on the 1st, but the days already gone have nothing left to show:
     * the chips and their counts share the same days.
     */
    public function testNoShortcutStartsBeforeToday(): void
    {
        $today = new DateTimeImmutable('today');

        foreach (DateRangePreset::cases() as $preset) {
            self::assertGreaterThanOrEqual($today, $preset->range()->from, $preset->value);
        }

        self::assertEquals($today, DateRangePreset::ThisWeek->range()->from);
        self::assertEquals($today, DateRangePreset::ThisMonth->range()->from);
        self::assertEquals(new DateTimeImmutable('sunday this week'), DateRangePreset::ThisWeek->range()->to);
    }

    /**
     * The weekly e-mail reads "Ce week-end" on the day it is sent: a Thursday's is the next three days, a Saturday's
     * what is left of it.
     */
    public function testAShortcutIsReadOnTheDayGiven(): void
    {
        $thursday = DateRangePreset::ThisWeekend->range(new DateTimeImmutable('2026-10-22 08:00'));
        self::assertEquals(new DateTimeImmutable('2026-10-23'), $thursday->from);
        self::assertEquals(new DateTimeImmutable('2026-10-25'), $thursday->to);

        $saturday = DateRangePreset::ThisWeekend->range(new DateTimeImmutable('2026-10-24'));
        self::assertEquals(new DateTimeImmutable('2026-10-24'), $saturday->from);
        self::assertEquals(new DateTimeImmutable('2026-10-25'), $saturday->to);

        self::assertEquals(new DateTimeImmutable('2026-10-23'), DateRangePreset::Tomorrow->range(new DateTimeImmutable('2026-10-22'))->from);
    }

    public function testTousLesJoursHasNoEnd(): void
    {
        self::assertNull(DateRangePreset::Anytime->range()->to);
    }
}
