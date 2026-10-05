<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use App\Entity\Event;
use App\Entity\EventTimesheet;
use DateTimeInterface;

/**
 * The hours shown for a session or an event, built from their times at render time: the stored label only holds what
 * the times cannot say ("À 20h, de 21h à minuit"), and comes after them.
 */
final class SessionHours
{
    /**
     * The hours of a session: its times, then its label. A session with neither shows the hours of its event, the
     * default of the event form ("Ils valent pour chaque date qui n'a pas ses propres horaires").
     */
    public static function ofSession(EventTimesheet $session): ?string
    {
        $times = self::format($session->getStartTime(), $session->getEndTime());
        $hours = $session->getHours() ?? (null === $times ? $session->getEvent()?->getHours() : null);

        return implode(', ', array_filter([$times, $hours], static fn (?string $part): bool => null !== $part && '' !== $part)) ?: null;
    }

    /**
     * The hours every session of the event shares, null when they differ (each date then shows its own). An event
     * without timesheets is one session, at its own times.
     */
    public static function ofEvent(Event $event): ?string
    {
        $hours = array_unique(array_map(self::ofSession(...), $event->getSessions()), \SORT_REGULAR);

        return 1 === \count($hours) ? reset($hours) : null;
    }

    /**
     * The times of a slot as the site writes them, the dates aside: "À 20h30", "De 20h à 23h"… An end earlier than the
     * start goes past midnight. Null without any time.
     */
    public static function format(?DateTimeInterface $start, ?DateTimeInterface $end): ?string
    {
        if (null !== $start && null !== $end) {
            return \sprintf('De %s à %s', self::time($start), self::time($end));
        }

        if (null !== $start) {
            return \sprintf('À %s', self::time($start));
        }

        // A source sending only when it closes
        return null === $end ? null : \sprintf("Jusqu'à %s", self::time($end));
    }

    /**
     * "20h", "20h30", "9h05", and midnight and noon in words.
     */
    private static function time(DateTimeInterface $time): string
    {
        return match ($time->format('H:i')) {
            '00:00' => 'minuit',
            '12:00' => 'midi',
            default => $time->format('G\h') . ('00' === $time->format('i') ? '' : $time->format('i')),
        };
    }
}
