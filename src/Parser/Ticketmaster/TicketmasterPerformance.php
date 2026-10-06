<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Ticketmaster;

use DateTimeImmutable;

/**
 * One date of a show on sale at Ticketmaster France.
 */
final readonly class TicketmasterPerformance
{
    public function __construct(
        /** The local day, at midnight */
        public DateTimeImmutable $date,
        /** The local start time (its date is not read) */
        public ?DateTimeImmutable $time,
        /** As the feed says it: "onsale", "offsale", "rescheduled" or "cancelled" */
        public string $status,
    ) {
    }
}
