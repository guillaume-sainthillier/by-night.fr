<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Ticketing;

/**
 * Where to book an event: one ticketing site, or the organizer's own booking.
 */
final readonly class TicketOffer
{
    /**
     * @param array{label: string, free: bool}|null $priceLabel what the source says of its prices, as a badge (PriceLabel)
     */
    public function __construct(
        public string $seller,
        public string $url,
        public bool $affiliate,
        public ?float $startingPrice,
        public ?array $priceLabel,
        public bool $soldOut,
    ) {
    }

    /**
     * Cheapest first; a sold-out offer, or one without a price, after those that can be bought at a known price.
     */
    public static function compare(self $a, self $b): int
    {
        return [$a->soldOut, null === $a->startingPrice, $a->startingPrice, $a->seller]
            <=> [$b->soldOut, null === $b->startingPrice, $b->startingPrice, $b->seller];
    }
}
