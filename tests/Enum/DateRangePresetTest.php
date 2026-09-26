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

    public function testTousLesJoursHasNoEnd(): void
    {
        self::assertNull(DateRangePreset::Anytime->range()->to);
    }
}
