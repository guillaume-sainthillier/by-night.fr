<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Ticketmaster;

/**
 * A show of Ticketmaster France ("manifestation", its id is the idmanif of its URLs) with all its dates.
 */
final readonly class TicketmasterShow
{
    /**
     * @param list<TicketmasterPerformance> $performances in chronological order
     */
    public function __construct(
        /** 2048px wide for most shows */
        public ?string $imageUrl,
        public ?float $latitude,
        public ?float $longitude,
        public array $performances,
    ) {
    }
}
