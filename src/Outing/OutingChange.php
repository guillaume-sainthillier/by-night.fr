<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Outing;

use App\Enum\EventStatus;

/**
 * What changed in an outing that its member should hear of (OutingChanges), the most serious first: the first one of
 * an alert names its subject.
 */
enum OutingChange: string
{
    case Cancelled = 'cancelled';
    case Removed = 'removed';
    case Postponed = 'postponed';
    case Rescheduled = 'rescheduled';
    case MovedOnline = 'moved_online';
    case Dates = 'dates';
    case Venue = 'venue';

    /**
     * The change a new status of the event tells, if it changes the member's plans (a sold-out event does not: they
     * may have their ticket).
     */
    public static function fromStatus(?EventStatus $status): ?self
    {
        return match ($status) {
            EventStatus::Cancelled => self::Cancelled,
            EventStatus::Removed => self::Removed,
            EventStatus::Postponed => self::Postponed,
            EventStatus::Rescheduled => self::Rescheduled,
            EventStatus::MovedOnline => self::MovedOnline,
            default => null,
        };
    }

    /**
     * The start of the subject of the alert: "Annulé : Concert".
     */
    public function getSubject(): string
    {
        return match ($this) {
            self::Cancelled => 'Annulé',
            self::Removed => "Retiré par l'organisateur",
            self::Postponed => 'Reporté',
            self::Rescheduled => 'Nouvelles dates',
            self::MovedOnline => 'Désormais en ligne',
            self::Dates => 'Changement de dates',
            self::Venue => 'Changement de lieu',
        };
    }
}
