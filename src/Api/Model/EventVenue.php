<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Model;

use App\Entity\Event;

/**
 * Where an event takes place, as its page writes it.
 */
final readonly class EventVenue
{
    public function __construct(
        public string $name,
        public ?string $address = null,
        public ?string $postalCode = null,
        public ?string $city = null,
    ) {
    }

    public static function of(Event $event): ?self
    {
        $name = $event->getPlaceName();
        if (null === $name || '' === $name) {
            return null;
        }

        $blankToNull = static fn (?string $value): ?string => '' === $value ? null : $value;

        return new self($name, $blankToNull($event->getPlaceStreet()), $blankToNull($event->getPlacePostalCode()), $blankToNull($event->getPlaceCity()));
    }
}
