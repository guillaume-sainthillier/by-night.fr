<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Model;

/**
 * An event as its card shows it, with the address of its page, where a reader is sent (its tickets, its map, its
 * details). The texts are in French.
 */
final readonly class EventSummary
{
    public function __construct(
        public int $id,
        public string $name,
        /** Its page on By Night */
        public string $url,
        public ?string $category,
        /** The first session from the first day asked for */
        public ?EventSession $nextSession,
        /** How many sessions are left from that day */
        public int $upcomingSessions,
        public ?EventVenue $venue,
        /** As its card writes it: "Gratuit", "À partir de 12 €" */
        public ?string $price,
        /** Whether it is free to get in */
        public ?bool $free,
        /** Cancelled, postponed, sold out…: none when it goes on as planned */
        public ?string $status,
        /** Who publishes it on By Night */
        public ?string $organizer,
    ) {
    }
}
