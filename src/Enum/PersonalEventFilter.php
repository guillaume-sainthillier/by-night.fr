<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

/**
 * The status filters of an organizer's list of events (/espace-perso/mes-soirees): each one keeps the events one of
 * the list's switches is set for, "Visible" on or off, "Annulé" on.
 */
enum PersonalEventFilter: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Visible => 'En ligne',
            self::Hidden => 'Masqués',
            self::Cancelled => 'Annulés',
        };
    }
}
