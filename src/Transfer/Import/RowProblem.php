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
 * What the check found wrong with a row (an error: its event is not imported) or worth a look (a warning).
 */
final readonly class RowProblem
{
    public function __construct(
        public int $row,
        public ?EventColumn $column,
        public string $message,
        public ?string $reference = null,
        public ?string $url = null,
    ) {
    }

    public static function at(SheetRow $row, ?EventColumn $column, string $message): self
    {
        $reference = $row->get(EventColumn::Reference);
        if ('' === $reference) {
            $reference = $row->get(EventColumn::Id);
        }

        return new self($row->number, $column, $message, '' === $reference ? null : $reference);
    }

    public function withUrl(string $url): self
    {
        return new self($this->row, $this->column, $this->message, $this->reference, $url);
    }

    /**
     * As EventImport keeps it.
     *
     * @return array{row: int, reference: ?string, column: ?string, message: string, url?: string}
     */
    public function toArray(): array
    {
        return ['row' => $this->row, 'reference' => $this->reference, 'column' => $this->column?->getLabel(), 'message' => $this->message]
            + (null === $this->url ? [] : ['url' => $this->url]);
    }

    /**
     * As the error file's "Erreurs" column shows it.
     */
    public function toCell(): string
    {
        return null === $this->column ? $this->message : \sprintf('%s : %s', $this->column->getLabel(), $this->message);
    }
}
