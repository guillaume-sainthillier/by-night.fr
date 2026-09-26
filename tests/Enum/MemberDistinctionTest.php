<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Enum;

use App\Entity\User;
use App\Enum\MemberDistinction;
use App\Stats\MemberActivity;
use App\Stats\MemberStats;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MemberDistinctionTest extends TestCase
{
    private const string TODAY = '2026-09-26';

    public function testAnOrganizerPublishedTenEventsOrMore(): void
    {
        self::assertSame([MemberDistinction::Organizer], $this->earnedBy(new MemberStats(new MemberActivity([]), publishedEvents: 10)));
        self::assertSame([], $this->earnedBy(new MemberStats(new MemberActivity([]), publishedEvents: 9)));
    }

    public function testACommenterWroteFiveCommentsOrMore(): void
    {
        self::assertSame([MemberDistinction::Commenter], $this->earnedBy(new MemberStats(new MemberActivity([]), comments: 5)));
        self::assertSame([], $this->earnedBy(new MemberStats(new MemberActivity([]), comments: 4)));
    }

    public function testAVeteranHasHadAnAccountForTenYearsOrMore(): void
    {
        self::assertSame([MemberDistinction::Veteran], $this->earnedBy($this->stats([]), '2016-09-26'));
        self::assertSame([], $this->earnedBy($this->stats([]), '2016-09-27'));
    }

    public function testARegularWentOutInThreeDifferentYears(): void
    {
        self::assertSame([MemberDistinction::Regular], $this->earnedBy($this->stats(['2014-06-11' => 1, '2020-01-01' => 1, '2026-09-01' => 1])));
        self::assertSame([], $this->earnedBy($this->stats(['2020-06-11' => 5, '2026-09-01' => 5])));
    }

    public function testANightOwlGoesOutOnFridayOrSaturdayHalfOfTheTimeAtLeast(): void
    {
        // 5 Fridays and 5 Mondays of 2024
        $days = $this->days('2024-01-05', 5) + $this->days('2024-01-01', 5);

        self::assertSame([MemberDistinction::NightOwl], $this->earnedBy($this->stats($days)));
        // One outing more on a Monday tips the balance
        self::assertSame([], $this->earnedBy($this->stats($days + ['2024-02-05' => 1])));
    }

    public function testTwoFridaysOutOfTwoAreNotEnoughToTellANightOwl(): void
    {
        self::assertSame([], $this->earnedBy($this->stats($this->days('2024-01-05', 2))));
    }

    public function testAVenueRegularWentOutTenTimesAtTheSameVenue(): void
    {
        self::assertSame([MemberDistinction::VenueRegular], $this->earnedBy(new MemberStats(new MemberActivity([]), busiestPlaceEvents: 10)));
        self::assertSame([], $this->earnedBy(new MemberStats(new MemberActivity([]), busiestPlaceEvents: 9)));
    }

    public function testAnExplorerWentOutInFiveCities(): void
    {
        self::assertSame([MemberDistinction::Explorer], $this->earnedBy(new MemberStats(new MemberActivity([]), cities: 5)));
        self::assertSame([], $this->earnedBy(new MemberStats(new MemberActivity([]), cities: 4)));
    }

    public function testAPlannerAddedHalfOfTheirOutingsAtLeastLongBefore(): void
    {
        // 10 Mondays
        $activity = new MemberActivity($this->days('2024-01-01', 10));

        self::assertSame([MemberDistinction::Planner], $this->earnedBy(new MemberStats($activity, eventsAddedAhead: 5)));
        self::assertSame([], $this->earnedBy(new MemberStats($activity, eventsAddedAhead: 4)));
        self::assertSame([], $this->earnedBy(new MemberStats(new MemberActivity($this->days('2024-01-01', 2)), eventsAddedAhead: 2)));
    }

    public function testABargainHunterWentToFreeEventsHalfOfTheTimeAtLeast(): void
    {
        // 10 Mondays
        $activity = new MemberActivity($this->days('2024-01-01', 10));

        self::assertSame([MemberDistinction::BargainHunter], $this->earnedBy(new MemberStats($activity, freeEvents: 5)));
        self::assertSame([], $this->earnedBy(new MemberStats($activity, freeEvents: 4)));
        self::assertSame([], $this->earnedBy(new MemberStats(new MemberActivity($this->days('2024-01-01', 2)), freeEvents: 2)));
    }

    public function testTheDistinctionsComeInTheOrderOfTheCases(): void
    {
        // 10 Fridays over three years
        $activity = new MemberActivity($this->days('2024-01-05', 8) + ['2014-01-03' => 1, '2020-01-03' => 1]);
        $stats = new MemberStats($activity, publishedEvents: 10, comments: 5, cities: 5, busiestPlaceEvents: 10, eventsAddedAhead: 10, freeEvents: 10);

        self::assertSame(MemberDistinction::cases(), $this->earnedBy($stats, '2013-10-14'));
    }

    /**
     * @return list<MemberDistinction>
     */
    private function earnedBy(MemberStats $stats, string $createdAt = '2024-01-01'): array
    {
        $user = new User();
        $user->setCreatedAt(new DateTimeImmutable($createdAt));

        return MemberDistinction::earnedBy($user, $stats, new DateTimeImmutable(self::TODAY));
    }

    /**
     * @param array<string, int> $eventsByDay
     */
    private function stats(array $eventsByDay): MemberStats
    {
        return new MemberStats(new MemberActivity($eventsByDay));
    }

    /**
     * @return array<string, int> one event on each of $weeks weeks in a row, from $first on
     */
    private function days(string $first, int $weeks): array
    {
        $days = [];
        foreach (range(0, $weeks - 1) as $week) {
            $days[new DateTimeImmutable($first)->modify(\sprintf('+%d weeks', $week))->format('Y-m-d')] = 1;
        }

        return $days;
    }
}
