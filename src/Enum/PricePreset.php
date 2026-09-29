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
 * The price shortcuts of the agenda, which its URLs name ("?price=under_20"): the events whose lowest price to get in
 * (Event::$startingPrice) is at most the shortcut's. An event without a known price is in none of them.
 */
enum PricePreset: string
{
    case Free = 'free';
    case Under10 = 'under_10';
    case Under20 = 'under_20';
    case Under50 = 'under_50';

    public function getLabel(): string
    {
        return match ($this) {
            self::Free => 'Gratuit',
            self::Under10 => "Jusqu'à 10\u{a0}€",
            self::Under20 => "Jusqu'à 20\u{a0}€",
            self::Under50 => "Jusqu'à 50\u{a0}€",
        };
    }

    /**
     * The highest starting price of the shortcut, in euros: 0 for the free entries.
     */
    public function getMaxPrice(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Under10 => 10,
            self::Under20 => 20,
            self::Under50 => 50,
        };
    }
}
