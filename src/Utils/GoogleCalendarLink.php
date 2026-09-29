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

/**
 * The "Ajouter à l'agenda" link of an event page: Google Calendar's event template, filled with the event's name,
 * dates, venue and a link back to its page. Nothing is stored on our side: the visitor saves the event in Google.
 */
final class GoogleCalendarLink
{
    private const string TEMPLATE_URL = 'https://calendar.google.com/calendar/render';

    /**
     * @return string|null null when the event has no date to put in a calendar
     */
    public static function forEvent(Event $event, string $eventUrl): ?string
    {
        $dates = self::dates($event);
        if (null === $dates) {
            return null;
        }

        return self::TEMPLATE_URL . '?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => $event->getName(),
            'dates' => $dates,
            'details' => \sprintf('Tous les détails sur By Night : %s', $eventUrl),
            'location' => self::location($event),
        ], '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * Google's "dates" parameter for whole days, "start/end" with the end day EXCLUDED: an event on the 26th and 27th
     * gives "20260926/20260928". Never a time slot: the hours are free text from the source or the organizer ("de
     * 10h30 à 20h", "Ouverture des portes à 19h, concert à 20h30…"), and one slot over several days would block them
     * all from the first opening to the last closing.
     */
    private static function dates(Event $event): ?string
    {
        $firstDay = $event->getStartDate();
        if (null === $firstDay) {
            return null;
        }

        $lastDay = max($firstDay, $event->getEndDate() ?? $firstDay);

        return \sprintf('%s/%s', $firstDay->format('Ymd'), $lastDay->modify('+1 day')->format('Ymd'));
    }

    /**
     * "Halle aux Toiles, 19 place de la Basse Vieille Tour, 76000 Rouen": what Google Maps finds best.
     */
    private static function location(Event $event): string
    {
        $city = trim(\sprintf('%s %s', $event->getPlacePostalCode(), $event->getPlaceCity()));

        return implode(', ', array_filter(
            [$event->getPlaceName(), $event->getPlaceStreet(), $city],
            static fn (?string $part): bool => null !== $part && '' !== $part,
        ));
    }
}
