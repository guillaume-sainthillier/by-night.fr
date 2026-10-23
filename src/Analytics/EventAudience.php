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
 * The audience of an event page shown to its author: its last days one by one, and what they add up to.
 */
final readonly class EventAudience
{
    /**
     * @param list<array{day: DateTimeImmutable, visitors: int, ticketClicks: int}> $days                 oldest first, today last
     * @param array{visitors: int, ticketClicks: int}                               $total                since the first visit counted
     * @param ?DateTimeImmutable                                                    $googleAnalyticsUntil the last day read from Google
     *                                                                                                    Analytics (only the visitors who
     *                                                                                                    accepted its cookies), if any
     */
    public function __construct(
        public array $days,
        public array $total,
        public ?DateTimeImmutable $googleAnalyticsUntil = null,
    ) {
    }

    /**
     * What the last $count days add up to, today included.
     *
     * @return array{visitors: int, ticketClicks: int}
     */
    public function last(int $count): array
    {
        $days = \array_slice($this->days, -$count);

        return [
            'visitors' => array_sum(array_column($days, 'visitors')),
            'ticketClicks' => array_sum(array_column($days, 'ticketClicks')),
        ];
    }

    /**
     * The most visitors of a day, to scale the chart (1 when there is none).
     */
    public function peakVisitors(): int
    {
        return max(1, ...array_column($this->days, 'visitors'));
    }
}
