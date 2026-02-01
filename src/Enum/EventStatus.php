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
    case Scheduled = 'scheduled';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case SoldOut = 'sold_out';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Programmé',
            self::Postponed => 'Reporté',
            self::Cancelled => 'Annulé',
            self::SoldOut => 'Complet',
        };
    }

    public function getSchemaOrgStatus(): string
    {
        return match ($this) {
            self::Scheduled => 'https://schema.org/EventScheduled',
            self::Postponed => 'https://schema.org/EventPostponed',
            self::Cancelled => 'https://schema.org/EventCancelled',
            self::SoldOut => 'https://schema.org/EventScheduled',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }

        return $choices;
    }
}
