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
 * Writes a member's export of events (App\Transfer\Export\EventExporter), on the "transfers" transport.
 */
final readonly class RunEventExport
{
    public function __construct(
        public int $exportId,
    ) {
    }
}
