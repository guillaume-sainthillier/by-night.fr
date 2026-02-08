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
        };
    }

    public function getSchemaOrgStatus(): string
    {
        return match ($this) {
            self::Postponed => 'https://schema.org/EventPostponed',
            self::Cancelled => 'https://schema.org/EventCancelled',
            self::SoldOut => 'https://schema.org/EventScheduled',
        };
    }
}
