<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Analytics;

use App\Repository\EventVisitStatRepository;
use DateTimeImmutable;

/**
 * Copies a day of the event pages' audience from Umami into event_visit_stat, where their authors read it: the
 * visitors of each page and the clicks on its ticketing links (the "ticket-click" event of
 * location/event/_tickets.html.twig). A page is found by the id ending its path, whatever its slug or location: a page
 * renamed during the day adds up. A duplicate's page redirects to its canonical's, which gets its visits.
 */
final readonly class EventVisitSync
{
    public const string TICKET_CLICK_EVENT = 'ticket-click';

    /** What every event page's path contains (app_event_details) */
    private const string EVENT_PATH = '/soiree/';

    public function __construct(
        private UmamiClient $umamiClient,
        private EventVisitStatRepository $eventVisitStatRepository,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->umamiClient->isConfigured();
    }

    /**
     * Replaces the audience of $day (Paris time) with Umami's: a day read again, today hour after hour, is up to date.
     *
     * @return array{events: int, visitors: int, ticketClicks: int} the events written, and what they add up to
     */
    public function sync(DateTimeImmutable $day): array
    {
        $from = $day->setTime(0, 0);
        $to = $from->modify('+1 day -1 millisecond');

        /** @var array<int, array{visitors: int, ticketClicks: int}> $stats */
        $stats = [];
        foreach ($this->umamiClient->pathVisitors($from, $to, self::EVENT_PATH) as $path => $visitors) {
            $id = self::eventIdOf($path);
            if (null !== $id) {
                $stats[$id] ??= ['visitors' => 0, 'ticketClicks' => 0];
                $stats[$id]['visitors'] += $visitors;
            }
        }

        foreach ($this->umamiClient->eventPaths(self::TICKET_CLICK_EVENT, $from, $to) as $path) {
            $id = self::eventIdOf($path);
            if (null !== $id) {
                $stats[$id] ??= ['visitors' => 0, 'ticketClicks' => 0];
                ++$stats[$id]['ticketClicks'];
            }
        }

        return [
            'events' => $this->eventVisitStatRepository->replaceDay($from, $stats),
            'visitors' => array_sum(array_column($stats, 'visitors')),
            'ticketClicks' => array_sum(array_column($stats, 'ticketClicks')),
        ];
    }

    /**
     * The event of a page path: /{location}/soiree/{slug}--{id}. Umami keeps the query string apart; the former
     * /soiree/{slug} URLs only redirect, and no page of another kind ends that way.
     */
    public static function eventIdOf(string $path): ?int
    {
        return 1 === preg_match('~/soiree/[^/]*--(\d+)$~', $path, $matches) ? (int) $matches[1] : null;
    }
}
