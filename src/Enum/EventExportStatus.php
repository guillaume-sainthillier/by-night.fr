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
 * Where a member's export of events stands: pending → in_progress → completed | failed.
 */
enum EventExportStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::InProgress => 'Export en cours',
            self::Completed => 'Prêt',
            self::Failed => 'Échec',
        };
    }

    /** The Tabler colour of its badge */
    public function getColor(): string
    {
        return match ($this) {
            self::Pending, self::InProgress => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }

    public function isRunning(): bool
    {
        return self::Pending === $this || self::InProgress === $this;
    }
}
