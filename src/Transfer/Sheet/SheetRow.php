<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Sheet;

/**
 * A row of a member's spreadsheet: its cells as written (copied as is into the check's report files) and the values
 * of the columns it recognised, as text.
 */
final readonly class SheetRow
{
    /**
     * @param int                   $number the row's number in the spreadsheet, the header being row 1
     * @param list<string>          $cells
     * @param array<string, string> $values by EventColumn value, trimmed
     */
    public function __construct(
        public int $number,
        public array $cells,
        public array $values,
    ) {
    }

    public function get(EventColumn $column): string
    {
        return $this->values[$column->value] ?? '';
    }

    public function has(EventColumn $column): bool
    {
        return \array_key_exists($column->value, $this->values);
    }
}
