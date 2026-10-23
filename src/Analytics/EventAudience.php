<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Analytics;

use App\Search\DateRange;
use DateTimeImmutable;

/**
 * The audience of an event page shown to its author: the days of a period one by one, what they add up to, and the
 * visitors since the first one counted.
 */
final readonly class EventAudience
{
    /**
     * @param DateRange                                                                                        $range the period shown
     * @param list<array{day: DateTimeImmutable, visitors: int, ticketClicks: int, fromGoogleAnalytics: bool}> $days  each day of the period, oldest first
     * @param array{visitors: int, ticketClicks: int}                                                          $total since the first visit counted
     */
    public function __construct(
        public DateRange $range,
        public array $days,
        public array $total,
    ) {
    }

    /**
     * What the days of the period add up to.
     *
     * @return array{visitors: int, ticketClicks: int}
     */
    public function sum(): array
    {
        return [
            'visitors' => array_sum(array_column($this->days, 'visitors')),
            'ticketClicks' => array_sum(array_column($this->days, 'ticketClicks')),
        ];
    }

    /**
     * The share of the period's visitors who clicked a ticketing link, null without visitors.
     */
    public function clickRate(): ?float
    {
        $sum = $this->sum();

        return $sum['visitors'] > 0 ? $sum['ticketClicks'] / $sum['visitors'] : null;
    }

    /**
     * The last day of the period read from Google Analytics (only the visitors who accepted its cookies), if any.
     */
    public function googleAnalyticsUntil(): ?DateTimeImmutable
    {
        $days = array_filter($this->days, static fn (array $day): bool => $day['fromGoogleAnalytics']);

        return [] === $days ? null : end($days)['day'];
    }

    public function chart(): AudienceChart
    {
        return new AudienceChart($this->days);
    }
}
