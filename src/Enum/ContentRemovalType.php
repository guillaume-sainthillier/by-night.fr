<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

enum ContentRemovalType: string
{
    case Image = 'image';
    case Event = 'event';

    public function getLabel(): string
    {
        return match ($this) {
            self::Image => 'Image de couverture',
            self::Event => 'Événement complet',
        };
    }
}
