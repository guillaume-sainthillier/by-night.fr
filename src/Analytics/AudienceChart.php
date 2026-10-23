<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Analytics;

use DateTimeImmutable;

/**
 * The line chart of an event's statistics, drawn server side as SVG paths (no chart library): a point a day, in a
 * WIDTH × HEIGHT box the page stretches to its width (personal-space/stats.html.twig). The axis goes up in round
 * steps (about a quarter of the peak) to the first one above the peak.
 */
final readonly class AudienceChart
{
    public const int WIDTH = 1000;

    public const int HEIGHT = 300;

    private const int TICKS = 4;

    private int $step;

    /** How many steps the axis goes up: TICKS at most */
    private int $steps;

    /**
     * @param list<array{day: DateTimeImmutable, visitors: int, ticketClicks: int, fromGoogleAnalytics: bool}> $days
     */
    public function __construct(
        private array $days,
    ) {
        $peak = max(0, ...array_column($days, 'visitors'), ...array_column($days, 'ticketClicks'));
        $this->step = self::niceStep($peak / self::TICKS);
        $this->steps = max(1, (int) ceil($peak / $this->step));
    }

    /**
     * The line of a series ("visitors" or "ticketClicks"), as an SVG path.
     */
    public function line(string $series): string
    {
        $points = array_map(fn (int $i): string => $this->x($i) . ',' . $this->y($this->days[$i][$series]), array_keys($this->days));

        return 'M' . implode(' L', $points);
    }

    /**
     * The visitors' line closed down to the axis, to fill below it.
     */
    public function area(): string
    {
        return \sprintf('%s L%s,%d L%s,%d Z', $this->line('visitors'), $this->x(\count($this->days) - 1), self::HEIGHT, $this->x(0), self::HEIGHT);
    }

    /**
     * The values of the axis, bottom first, and how high each sits (% of the height).
     *
     * @return list<array{value: int, bottom: float}>
     */
    public function ticks(): array
    {
        return array_map(
            fn (int $i): array => ['value' => $i * $this->step, 'bottom' => round(100 * $i / $this->steps, 2)],
            range(0, $this->steps),
        );
    }

    /**
     * A few days to name under the chart, the first and the last included, and where each sits (% of the width).
     *
     * @return list<array{day: DateTimeImmutable, left: float}>
     */
    public function labels(int $count = 6): array
    {
        $last = \count($this->days) - 1;
        $indexes = $last < $count ? range(0, $last) : array_unique(array_map(static fn (int $i): int => (int) round($i * $last / ($count - 1)), range(0, $count - 1)));

        return array_values(array_map(fn (int $i): array => ['day' => $this->days[$i]['day'], 'left' => round(100 * $this->x($i) / self::WIDTH, 2)], $indexes));
    }

    /**
     * A column a day across the whole height, which says the day's figures when hovered.
     *
     * @return list<array{day: DateTimeImmutable, visitors: int, ticketClicks: int, x: float, width: float}>
     */
    public function columns(): array
    {
        $width = self::WIDTH / max(1, \count($this->days) - 1);

        return array_map(fn (int $i): array => [
            ...$this->days[$i],
            'x' => max(0.0, $this->x($i) - $width / 2),
            'width' => $width,
        ], array_keys($this->days));
    }

    /**
     * Where the days read from Google Analytics lie, to shade them: from and to (SVG units), null without any.
     *
     * @return array{x: float, width: float}|null
     */
    public function googleAnalyticsSpan(): ?array
    {
        $indexes = array_keys(array_filter($this->days, static fn (array $day): bool => $day['fromGoogleAnalytics']));
        if ([] === $indexes) {
            return null;
        }

        $half = self::WIDTH / max(1, \count($this->days) - 1) / 2;
        $from = max(0.0, $this->x(min($indexes)) - $half);
        $to = min((float) self::WIDTH, $this->x(max($indexes)) + $half);

        return ['x' => $from, 'width' => $to - $from];
    }

    private function x(int $i): float
    {
        $count = \count($this->days);

        return round($count > 1 ? $i * self::WIDTH / ($count - 1) : self::WIDTH / 2, 2);
    }

    private function y(int $value): float
    {
        return round(self::HEIGHT - $value * self::HEIGHT / ($this->step * $this->steps), 2);
    }

    /**
     * The round step at least $rough: 1, 2, 5, 10, 20, 25, 50… (1 for nothing to show).
     */
    private static function niceStep(float $rough): int
    {
        if ($rough <= 1) {
            return 1;
        }

        $magnitude = 10 ** (int) floor(log10($rough));
        foreach ([1, 2, 2.5, 5, 10] as $factor) {
            if ($factor * $magnitude >= $rough && (int) ($factor * $magnitude) == $factor * $magnitude) {
                return (int) ($factor * $magnitude);
            }
        }

        return 10 * $magnitude;
    }
}
