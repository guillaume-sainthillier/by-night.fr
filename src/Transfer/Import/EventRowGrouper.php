<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Import;

use App\Transfer\Sheet\EventColumn;
use App\Transfer\Sheet\SheetRow;

/**
 * Splits a spreadsheet's rows into events: the rows of the same ID, or without one of the same reference, wherever
 * they are in the file. A row with neither is turned down, since nothing would tell its event apart the next time
 * the file is imported: it would be created twice.
 */
final class EventRowGrouper
{
    /**
     * @param list<SheetRow> $rows
     *
     * @return list<EventRowGroup>
     */
    public function group(array $rows): array
    {
        /** @var array<string, non-empty-list<SheetRow>> $byKey */
        $byKey = [];
        $groups = [];
        foreach ($rows as $row) {
            $id = $row->get(EventColumn::Id);
            $reference = $row->get(EventColumn::Reference);
            if ('' !== $id && 1 !== preg_match('/^\d{1,10}$/', $id)) {
                $groups[] = new EventRowGroup([$row], [RowProblem::at($row, EventColumn::Id, \sprintf('« %s » n’est pas un ID de By Night : laissez la colonne vide pour un nouvel événement.', $id))]);
            } elseif ('' !== $id) {
                $byKey['id:' . (int) $id][] = $row;
            } elseif ('' !== $reference) {
                $byKey['reference:' . mb_strtolower($reference)][] = $row;
            } else {
                $groups[] = new EventRowGroup([$row], [RowProblem::at($row, EventColumn::Reference, 'Donnez une référence à l’événement (ou gardez l’ID de l’export) : sans elle, un nouvel import le créerait une seconde fois.')]);
            }
        }

        // One reference names one event: given to two (two IDs, or an ID and new rows), the later ones are turned down
        $owners = [];
        foreach ($byKey as $key => $groupRows) {
            $group = new EventRowGroup($groupRows);
            $reference = null === $group->reference() ? null : mb_strtolower($group->reference());
            if (null !== $reference && isset($owners[$reference])) {
                $group = new EventRowGroup($groupRows, [RowProblem::at($group->first(), EventColumn::Reference, \sprintf('La référence « %s » est déjà celle de l’événement de la ligne %d.', $group->reference(), $owners[$reference]))]);
            } elseif (null !== $reference) {
                $owners[$reference] = $group->first()->number;
            }

            $groups[] = $group;
        }

        usort($groups, static fn (EventRowGroup $a, EventRowGroup $b): int => $a->first()->number <=> $b->first()->number);

        return $groups;
    }
}
