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
use DateTimeImmutable;
use DateTimeInterface;
use IntlDateFormatter;

/**
 * When an event of a list of suggestions happens, short: its next session, not the day it first started ("sam. 24 oct.
 * · 20h"), or the day a run still going ends ("Jusqu'au 5 nov."). The header search's suggestions.
 */
final class NextSessionLabel
{
    public static function of(Event $event, ?DateTimeInterface $today = null): ?string
    {
        $today = DateTimeImmutable::createFromInterface($today ?? new DateTimeImmutable())->setTime(0, 0);
        $session = $event->getSessionFor($today);
        $start = $session?->getStartAt();
        if (null === $start) {
            return null;
        }

        if ($start < $today) {
            return 'Jusqu\'au ' . self::format($session->getEndAt() ?? $start, 'd MMM');
        }

        $time = SessionHours::start($session);

        return self::format($start, 'EEE d MMM') . (null !== $time ? ' · ' . $time : '');
    }

    private static function format(DateTimeInterface $day, string $pattern): string
    {
        return (string) new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, $pattern)->format($day);
    }
}
