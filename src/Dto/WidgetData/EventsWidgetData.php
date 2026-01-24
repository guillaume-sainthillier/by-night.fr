<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto\WidgetData;

use App\Entity\Place;

final readonly class EventsWidgetData
{
    /**
     * @param array<\App\Entity\Event> $events
     */
    public function __construct(
        public int $page,
        public ?Place $place,
        public array $events,
        public int $current,
        public int $count,
        public ?string $hasNextLink,
    ) {
    }
}
