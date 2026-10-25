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
 * Where a member's import of events stands. It is checked first (nothing written), then imported on the member's
 * go, from the rows the check kept:
 * uploaded → checking → checked | check_failed | cancelled, then checked → importing → completed | failed.
 * A failed import can be resumed: the rows already written match their event and come out unchanged.
 */
enum EventImportStatus: string
{
    case Uploaded = 'uploaded';
    case Checking = 'checking';
    case Checked = 'checked';
    case CheckFailed = 'check_failed';
    case Cancelled = 'cancelled';
    case Importing = 'importing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Uploaded => 'En attente de vérification',
            self::Checking => 'Vérification en cours',
            self::Checked => 'Vérifié',
            self::CheckFailed => 'Vérification impossible',
            self::Cancelled => 'Annulé',
            self::Importing => 'Import en cours',
            self::Completed => 'Importé',
            self::Failed => 'Import interrompu',
        };
    }

    /** The Tabler colour of its badge */
    public function getColor(): string
    {
        return match ($this) {
            self::Uploaded, self::Checking, self::Importing => 'blue',
            self::Checked => 'cyan',
            self::Completed => 'green',
            self::CheckFailed, self::Failed => 'red',
            self::Cancelled => 'secondary',
        };
    }

    /**
     * The statuses it can be reached from: the whole graph, in one place. An import resumes from failed; a check
     * that lost its worker fails from uploaded (`app:transfers:cleanup`).
     *
     * @return list<self>
     */
    public function allowedFrom(): array
    {
        return match ($this) {
            self::Uploaded => [],
            self::Checking => [self::Uploaded],
            self::Checked => [self::Checking],
            self::CheckFailed => [self::Uploaded, self::Checking],
            self::Cancelled => [self::Uploaded, self::Checking, self::Checked],
            self::Importing => [self::Checked, self::Failed],
            self::Completed, self::Failed => [self::Importing],
        };
    }

    /** Whether a worker is on it (or about to be): the page follows its progress */
    public function isRunning(): bool
    {
        return \in_array($this, [self::Uploaded, self::Checking, self::Importing], true);
    }
}
