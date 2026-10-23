<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Analytics;

use App\Analytics\AudienceQuery;
use App\Enum\AudiencePeriod;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AudienceQueryTest extends TestCase
{
    public function testWithoutAnythingTheLast30DaysAreShown(): void
    {
        $query = new AudienceQuery();

        self::assertSame(AudiencePeriod::Last30Days, $query->getPreset());
        self::assertEquals(new DateTimeImmutable('today -29 days'), $query->getDateRange()->from);
        self::assertEquals(new DateTimeImmutable('today'), $query->getDateRange()->to);
    }

    public function testLastMonthIsItsWholeMonth(): void
    {
        $query = new AudienceQuery();
        $query->period = AudiencePeriod::LastMonth;

        self::assertEquals(new DateTimeImmutable('first day of last month midnight'), $query->getDateRange()->from);
        self::assertEquals(new DateTimeImmutable('last day of last month midnight'), $query->getDateRange()->to);
    }

    public function testTheDatesPickedWinOverTheShortcut(): void
    {
        $query = new AudienceQuery();
        $query->period = AudiencePeriod::Last7Days;
        $query->from = new DateTimeImmutable('2026-01-10');
        $query->to = new DateTimeImmutable('2026-01-20');

        self::assertNull($query->getPreset());
        self::assertSame('2026-01-10', $query->getDateRange()->from->format('Y-m-d'));
        self::assertSame('2026-01-20', $query->getDateRange()->to?->format('Y-m-d'));
    }

    public function testDatesPickedTheWrongWayRoundArePutInOrder(): void
    {
        $query = new AudienceQuery();
        $query->from = new DateTimeImmutable('2026-01-20');
        $query->to = new DateTimeImmutable('2026-01-10');

        self::assertSame('2026-01-10', $query->getDateRange()->from->format('Y-m-d'));
        self::assertSame('2026-01-20', $query->getDateRange()->to?->format('Y-m-d'));
    }

    public function testNoDayAfterTodayIsShown(): void
    {
        $query = new AudienceQuery();
        $query->from = new DateTimeImmutable('-3 days');
        $query->to = new DateTimeImmutable('+10 days');

        self::assertEquals(new DateTimeImmutable('today'), $query->getDateRange()->to);
    }

    public function testATooLongPeriodKeepsItsMostRecentDays(): void
    {
        $query = new AudienceQuery();
        $query->from = new DateTimeImmutable('2020-01-01');
        $query->to = new DateTimeImmutable('2026-01-31');

        $range = $query->getDateRange();

        self::assertSame('2026-01-31', $range->to?->format('Y-m-d'));
        self::assertSame(AudienceQuery::MAX_DAYS - 1, (int) $range->from->diff($range->to)->days);
    }
}
