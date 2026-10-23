<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Analytics;

use App\Entity\Event;
use App\Repository\EventVisitStatRepository;
use DateTimeImmutable;

/**
 * The audience of the member's events, read from what EventVisitSync copied. An event that turned out to be a
 * duplicate has no page of its own: it redirects to its canonical's, whose audience is its.
 */
final readonly class EventAudienceReport
{
    public const int DAYS = 30;

    private const array NONE = ['visitors' => 0, 'ticketClicks' => 0];

    public function __construct(
        private EventVisitStatRepository $eventVisitStatRepository,
    ) {
    }

    public function forEvent(Event $event): EventAudience
    {
        $id = (int) $event->getCanonicalEvent()->getId();
        $today = new DateTimeImmutable('today');
        $daily = $this->eventVisitStatRepository->findDaily($id, $today->modify(\sprintf('-%d days', self::DAYS - 1)));

        $days = [];
        for ($i = self::DAYS - 1; $i >= 0; --$i) {
            $day = $today->modify("-$i days");
            $days[] = ['day' => $day, ...$daily[$day->format('Y-m-d')] ?? self::NONE];
        }

        $total = $this->eventVisitStatRepository->findTotalsByEvents([$id], new DateTimeImmutable('2000-01-01'))[$id] ?? self::NONE;

        return new EventAudience($days, $total);
    }

    /**
     * The visitors and ticketing clicks of the last DAYS days of each event, by the id of the event given.
     *
     * @param iterable<Event> $events
     *
     * @return array<int, array{visitors: int, ticketClicks: int}>
     */
    public function lastDaysByEvent(iterable $events): array
    {
        $canonicalIds = [];
        foreach ($events as $event) {
            $canonicalIds[(int) $event->getId()] = (int) $event->getCanonicalEvent()->getId();
        }

        $since = new DateTimeImmutable(\sprintf('today -%d days', self::DAYS - 1));
        $totals = $this->eventVisitStatRepository->findTotalsByEvents(array_values($canonicalIds), $since);

        return array_map(static fn (int $canonicalId): array => $totals[$canonicalId] ?? self::NONE, $canonicalIds);
    }
}
