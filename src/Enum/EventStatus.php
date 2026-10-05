<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

enum EventStatus: string
{
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case SoldOut = 'sold_out';
    /** Taking place on new dates, which the event now has */
    case Rescheduled = 'rescheduled';
    /** Still taking place, online instead of at its venue */
    case MovedOnline = 'moved_online';
    /**
     * No longer listed by the source it was imported from (an OpenAgenda event removed from
     * its agenda, a DATAtourisme object made obsolete). The page stays, out of the listings.
     */
    case Removed = 'removed';

    public static function fromStatusMessage(?string $statusMessage): ?self
    {
        if (null === $statusMessage || '' === $statusMessage) {
            return null;
        }

        $statusLower = mb_strtolower($statusMessage);

        if (str_contains($statusLower, 'annul')) {
            return self::Cancelled;
        }

        if (str_contains($statusLower, 'report')) {
            return self::Postponed;
        }

        if (str_contains($statusLower, 'complet')) {
            return self::SoldOut;
        }

        return null;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Postponed => 'Reporté',
            self::Cancelled => 'Annulé',
            self::SoldOut => 'Complet',
            self::Rescheduled => 'Reprogrammé',
            self::MovedOnline => 'Déplacé en ligne',
            self::Removed => "Retiré de l'agenda de l'organisateur",
        };
    }

    public function getSchemaOrgStatus(): string
    {
        return match ($this) {
            self::Postponed => 'https://schema.org/EventPostponed',
            self::Cancelled => 'https://schema.org/EventCancelled',
            self::SoldOut => 'https://schema.org/EventScheduled',
            self::Rescheduled => 'https://schema.org/EventRescheduled',
            self::MovedOnline => 'https://schema.org/EventMovedOnline',
            // schema.org has no "unlisted": the page is noindex (the event is hidden), and nothing says it was cancelled
            self::Removed => 'https://schema.org/EventScheduled',
        };
    }
}
