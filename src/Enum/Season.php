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
 * The seasons a member's outings are shared between on their profile (/membres/{slug}--{id}).
 */
enum Season: string
{
    case Spring = 'spring';
    case Summer = 'summer';
    case Autumn = 'autumn';
    case Winter = 'winter';

    /**
     * The meteorological seasons (whole months) of the northern hemisphere, where every city of By Night is.
     *
     * @param int<1, 12> $month
     */
    public static function fromMonth(int $month): self
    {
        return match (true) {
            $month >= 3 && $month <= 5 => self::Spring,
            $month >= 6 && $month <= 8 => self::Summer,
            $month >= 9 && $month <= 11 => self::Autumn,
            default => self::Winter,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Spring => 'Printemps',
            self::Summer => 'Été',
            self::Autumn => 'Automne',
            self::Winter => 'Hiver',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Spring => 'lucide:flower-2',
            self::Summer => 'lucide:sun',
            self::Autumn => 'lucide:leaf',
            self::Winter => 'lucide:snowflake',
        };
    }
}
