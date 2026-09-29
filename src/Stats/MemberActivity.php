<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Stats;

use App\Enum\Season;
use DateTimeImmutable;

/**
 * When a member goes out, from the events of their calendar (EventRepository::countUserEventsByDay()): the months,
 * seasons and days of the week of their profile (/membres/{slug}--{id}).
 *
 * Built from one count per day rather than grouped in SQL by MONTH() or DAYOFWEEK(), which are MySQL functions: the
 * counts stay portable, and a member has a few thousand days at most.
 */
final readonly class MemberActivity
{
    /** @var array<int<1, 12>, int> */
    private array $byMonth;

    /** @var array<int<1, 7>, int> ISO-8601 days, from 1 (Monday) to 7 (Sunday) */
    private array $byWeekday;

    /** @var list<int> */
    private array $years;

    private int $total;

    /**
     * @param array<string, int> $eventsByDay the number of events of each day, keyed by date (Y-m-d)
     */
    public function __construct(array $eventsByDay)
    {
        $byMonth = array_fill(1, 12, 0);
        $byWeekday = array_fill(1, 7, 0);
        $years = [];

        foreach ($eventsByDay as $day => $count) {
            $date = new DateTimeImmutable((string) $day);
            $byMonth[(int) $date->format('n')] += $count;
            $byWeekday[(int) $date->format('N')] += $count;
            $years[(int) $date->format('Y')] = true;
        }

        $years = array_keys($years);
        sort($years);

        $this->byMonth = $byMonth;
        $this->byWeekday = $byWeekday;
        $this->years = $years;
        $this->total = array_sum($eventsByDay);
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return array<int<1, 12>, int> every month of the year, from 1 (January)
     */
    public function getByMonth(): array
    {
        return $this->byMonth;
    }

    /**
     * @return array<int<1, 7>, int> every day of the week, from 1 (Monday) to 7 (Sunday)
     */
    public function getByWeekday(): array
    {
        return $this->byWeekday;
    }

    /**
     * @return list<array{season: Season, count: int}> from spring to winter
     */
    public function getBySeason(): array
    {
        $bySeason = [];
        foreach (Season::cases() as $season) {
            $bySeason[$season->value] = ['season' => $season, 'count' => 0];
        }

        foreach ($this->byMonth as $month => $count) {
            $bySeason[Season::fromMonth($month)->value]['count'] += $count;
        }

        return array_values($bySeason);
    }

    /**
     * @return list<int<1, 12>> the busiest months, several when they tie; none without any event
     */
    public function getPeakMonths(): array
    {
        return $this->busiest($this->byMonth);
    }

    /**
     * @return list<int<1, 7>> the busiest days of the week, the busiest first; the days without any event are left out
     */
    public function getTopWeekdays(int $limit = 2): array
    {
        $byWeekday = array_filter($this->byWeekday);
        // Stable: the earlier day of the week first when two tie
        arsort($byWeekday);

        return \array_slice(array_keys($byWeekday), 0, $limit);
    }

    /**
     * @return int the share of a count in all the events, in percent
     */
    public function getShare(int $count): int
    {
        return 0 === $this->total ? 0 : (int) round($count * 100 / $this->total);
    }

    /**
     * @return list<int> the years the member went out, in order
     */
    public function getYears(): array
    {
        return $this->years;
    }

    /**
     * @template T of int
     *
     * @param array<T, int> $counts
     *
     * @return list<T>
     */
    private function busiest(array $counts): array
    {
        $max = max($counts);
        if (0 === $max) {
            return [];
        }

        return array_keys($counts, $max, true);
    }
}
