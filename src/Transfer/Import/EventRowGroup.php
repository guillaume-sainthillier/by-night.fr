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
 * The rows of a spreadsheet that are one event: one row per session, sharing an ID or a reference. Rows the grouping
 * itself turned down carry their errors and are no event.
 */
final readonly class EventRowGroup
{
    /**
     * @param non-empty-list<SheetRow> $rows   in the file's order
     * @param list<RowProblem>         $errors what the grouping found wrong
     */
    public function __construct(
        public array $rows,
        public array $errors = [],
    ) {
    }

    public function first(): SheetRow
    {
        return $this->rows[0];
    }

    public function id(): ?int
    {
        $id = $this->first()->get(EventColumn::Id);

        return '' === $id ? null : (int) $id;
    }

    public function reference(): ?string
    {
        foreach ($this->rows as $row) {
            if ('' !== $row->get(EventColumn::Reference)) {
                return $row->get(EventColumn::Reference);
            }
        }

        return null;
    }
}
