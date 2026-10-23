<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Analytics;

use App\Analytics\AudienceChart;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AudienceChartTest extends TestCase
{
    /**
     * @return iterable<string, array{int, list<int>}>
     */
    public static function providePeaks(): iterable
    {
        yield 'nothing' => [0, [0, 1]];
        yield 'a few' => [3, [0, 1, 2, 3]];
        yield 'seven' => [7, [0, 2, 4, 6, 8]];
        yield 'forty-two' => [42, [0, 20, 40, 60]];
        yield 'ninety' => [90, [0, 25, 50, 75, 100]];
        yield 'a thousand and one' => [1001, [0, 500, 1000, 1500]];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('providePeaks')]
    public function testTheAxisGoesUpInRoundStepsToTheFirstAboveThePeak(int $peak, array $expected): void
    {
        $chart = new AudienceChart([$this->day('2026-10-01', $peak), $this->day('2026-10-02', 0)]);

        self::assertSame($expected, array_column($chart->ticks(), 'value'));
        self::assertSame(100.0, array_column($chart->ticks(), 'bottom')[\count($expected) - 1]);
    }

    public function testTheLinesSpanTheWholeWidthFromTheBottomUp(): void
    {
        $chart = new AudienceChart([$this->day('2026-10-01', 0), $this->day('2026-10-02', 4, 2), $this->day('2026-10-03', 2)]);

        self::assertSame('M0,300 L500,0 L1000,150', $chart->line('visitors'));
        self::assertSame('M0,300 L500,150 L1000,300', $chart->line('ticketClicks'));
        self::assertSame('M0,300 L500,0 L1000,150 L1000,300 L0,300 Z', $chart->area());
    }

    public function testASingleDayStandsInTheMiddle(): void
    {
        $chart = new AudienceChart([$this->day('2026-10-01', 3)]);

        self::assertSame('M500,0', $chart->line('visitors'));
        self::assertSame([50.0], array_column($chart->labels(), 'left'));
    }

    public function testTheLabelsNameTheFirstAndTheLastDay(): void
    {
        $days = array_map(fn (int $i): array => $this->day(\sprintf('2026-10-%02d', $i), $i), range(1, 30));

        $labels = new AudienceChart($days)->labels();

        self::assertCount(6, $labels);
        self::assertSame('2026-10-01', $labels[0]['day']->format('Y-m-d'));
        self::assertSame('2026-10-30', $labels[5]['day']->format('Y-m-d'));
        self::assertSame(100.0, $labels[5]['left']);
    }

    /**
     * @return array{day: DateTimeImmutable, visitors: int, ticketClicks: int, fromGoogleAnalytics: bool}
     */
    private function day(string $day, int $visitors, int $ticketClicks = 0, bool $fromGoogleAnalytics = false): array
    {
        return ['day' => new DateTimeImmutable($day), 'visitors' => $visitors, 'ticketClicks' => $ticketClicks, 'fromGoogleAnalytics' => $fromGoogleAnalytics];
    }
}
