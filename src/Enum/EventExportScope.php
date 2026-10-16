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
 * Which of their events a member exports.
 */
enum EventExportScope: string
{
    case All = 'all';
    case Upcoming = 'upcoming';
    case Past = 'past';
    case Drafts = 'drafts';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'Tous mes événements',
            self::Upcoming => 'À venir',
            self::Past => 'Passés',
            self::Drafts => 'Brouillons',
        };
    }
}
