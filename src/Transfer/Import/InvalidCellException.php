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
use RuntimeException;

/**
 * A cell whose value cannot be read: its message is for the member.
 */
final class InvalidCellException extends RuntimeException
{
    /**
     * @param SheetRow|null $row the row the value is on, when it is not the event's first
     */
    public function __construct(public readonly EventColumn $column, string $message, public readonly ?SheetRow $row = null)
    {
        parent::__construct($message);
    }
}
