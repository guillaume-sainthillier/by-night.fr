<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Stats;

use App\Enum\Season;
use App\Stats\MemberActivity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MemberActivityTest extends TestCase
{
    public function testTheDaysAddUpByMonthAndDayOfTheWeek(): void
    {
        $activity = new MemberActivity([
            '2024-05-03' => 2, // a Friday of May
            '2024-05-04' => 1, // a Saturday of May
            '2025-06-06' => 3, // a Friday of June
            '2023-12-31' => 1, // a Sunday of December
        ]);

        self::assertSame(7, $activity->getTotal());
        self::assertSame([1 => 0, 0, 0, 0, 3, 3, 0, 0, 0, 0, 0, 1], $activity->getByMonth());
        self::assertSame([1 => 0, 0, 0, 0, 5, 1, 1], $activity->getByWeekday());
        self::assertSame([2023, 2024, 2025], $activity->getYears());
    }

    public function testTheMonthsAddUpBySeason(): void
    {
        $activity = new MemberActivity(['2024-02-29' => 1, '2024-03-01' => 2, '2024-08-31' => 3, '2024-11-30' => 4]);

        $bySeason = array_map(static fn (array $row): array => [$row['season'], $row['count']], $activity->getBySeason());

        self::assertSame([[Season::Spring, 2], [Season::Summer, 3], [Season::Autumn, 4], [Season::Winter, 1]], $bySeason);
    }

    public function testTheBusiestMonthsAreAllTheOnesThatTie(): void
    {
        $activity = new MemberActivity(['2024-05-01' => 3, '2024-06-01' => 3, '2024-07-01' => 1]);

        self::assertSame([5, 6], $activity->getPeakMonths());
    }

    public function testTheTopDaysOfTheWeekComeBusiestFirstAndLeaveTheEmptyDaysOut(): void
    {
        // A Monday, then a Saturday twice, then a Friday
        $activity = new MemberActivity(['2024-01-01' => 1, '2024-01-06' => 2, '2024-01-13' => 2, '2024-01-05' => 1]);

        self::assertSame([6, 1], $activity->getTopWeekdays());
        self::assertSame([6, 1, 5], $activity->getTopWeekdays(5));
    }

    public function testTheShareOfACountIsARoundedPercentage(): void
    {
        $activity = new MemberActivity(['2024-01-01' => 1, '2024-01-02' => 2]);

        self::assertSame(33, $activity->getShare(1));
        self::assertSame(67, $activity->getShare(2));
    }

    public function testAMemberWithoutAnyEventHasNoPeakNorShare(): void
    {
        $activity = new MemberActivity([]);

        self::assertSame(0, $activity->getTotal());
        self::assertSame([], $activity->getPeakMonths());
        self::assertSame([], $activity->getTopWeekdays());
        self::assertSame(0, $activity->getShare(0));
        self::assertSame([], $activity->getYears());
    }

    #[DataProvider('provideMonths')]
    public function testEachMonthBelongsToItsMeteorologicalSeason(int $month, Season $season): void
    {
        self::assertSame($season, Season::fromMonth($month));
    }

    /**
     * @return iterable<string, array{int, Season}>
     */
    public static function provideMonths(): iterable
    {
        yield 'January' => [1, Season::Winter];
        yield 'February' => [2, Season::Winter];
        yield 'March' => [3, Season::Spring];
        yield 'May' => [5, Season::Spring];
        yield 'June' => [6, Season::Summer];
        yield 'August' => [8, Season::Summer];
        yield 'September' => [9, Season::Autumn];
        yield 'November' => [11, Season::Autumn];
        yield 'December' => [12, Season::Winter];
    }
}
