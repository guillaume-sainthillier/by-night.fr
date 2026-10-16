<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Sheet;

use App\Enum\EventStatus;

/**
 * The "Statut" column: the statuses a member may give their event, by their label. "Retiré" is the import
 * pipeline's (a source dropped the event), never a member's.
 */
final class EventStatusLabels
{
    /** @var array<string, EventStatus> */
    public const array IMPORTABLE = [
        'Annulé' => EventStatus::Cancelled,
        'Reporté' => EventStatus::Postponed,
        'Complet' => EventStatus::SoldOut,
        'Reprogrammé' => EventStatus::Rescheduled,
        'Déplacé en ligne' => EventStatus::MovedOnline,
    ];

    /**
     * @return EventStatus|false|null the status, null for none (an empty cell), false for a value it is not
     */
    public static function fromCell(string $value): EventStatus|false|null
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $wanted = self::fold($value);
        foreach (self::IMPORTABLE as $label => $status) {
            if (self::fold($label) === $wanted || $status->value === $wanted) {
                return $status;
            }
        }

        return false;
    }

    public static function toCell(?EventStatus $status): string
    {
        return null === $status ? '' : (string) array_search($status, self::IMPORTABLE, true);
    }

    private static function fold(string $value): string
    {
        return mb_strtolower((string) transliterator_transliterate('Any-Latin; Latin-ASCII', trim($value)));
    }
}
