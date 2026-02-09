<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

enum ContentRemovalRequestStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Processed => 'Traité',
            self::Rejected => 'Rejeté',
        };
    }
}
