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
use LogicException;

/**
 * Fills a day before Umami with the event pages' visitors from Google Analytics, flagged as such: GA4 only saw the
 * visitors who accepted its cookies, and its outbound clicks cannot tell a ticketing link from the others, so the
 * clicks stay at 0. Never a day Umami has read.
 */
final readonly class EventVisitBackfill
{
    private const string EVENT_PATH = '/soiree/';

    /** The row GA4 puts the pages it no longer tells apart in */
    private const string OTHER_ROW = '(other)';

    public function __construct(
        private EventVisitStatRepository $eventVisitStatRepository,
    ) {
    }

    /**
     * @return array{events: int, visitors: int, unattributed: int} the events written (found, when $dryRun), their
     *                                                              visitors, and those GA4 left in "(other)"
     */
    public function import(GoogleAnalyticsClient $client, DateTimeImmutable $day, bool $dryRun = false): array
    {
        $day = $day->setTime(0, 0);
        $firstUmamiDay = $this->eventVisitStatRepository->findFirstUmamiDay();
        if (null !== $firstUmamiDay && $day >= $firstUmamiDay) {
            throw new LogicException(\sprintf('%s is read from Umami: Google Analytics only fills the days before %s.', $day->format('Y-m-d'), $firstUmamiDay->format('Y-m-d')));
        }

        /** @var array<int, array{visitors: int, ticketClicks: int}> $stats */
        $stats = [];
        $unattributed = 0;
        foreach ($client->pathVisitors($day, self::EVENT_PATH) as $path => $visitors) {
            if (self::OTHER_ROW === $path) {
                $unattributed += $visitors;
                continue;
            }

            $id = EventVisitSync::eventIdOf($path);
            if (null !== $id) {
                $stats[$id] ??= ['visitors' => 0, 'ticketClicks' => 0];
                $stats[$id]['visitors'] += $visitors;
            }
        }

        return [
            'events' => $dryRun ? \count($stats) : $this->eventVisitStatRepository->replaceDay($day, $stats, fromGoogleAnalytics: true),
            'visitors' => array_sum(array_column($stats, 'visitors')),
            'unattributed' => $unattributed,
        ];
    }
}
