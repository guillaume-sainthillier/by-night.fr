<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Message;

/**
 * Imports the events a member's spreadsheet check kept (App\Transfer\Import\EventImportRunner), on the "transfers"
 * transport.
 */
final readonly class RunEventImport
{
    public function __construct(
        public int $importId,
    ) {
    }
}
