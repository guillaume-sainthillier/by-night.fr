<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto\WidgetData;

use App\App\Location;

final readonly class TopEventsWidgetData
{
    /**
     * @param array<\App\Entity\Event> $events
     */
    public function __construct(
        public Location $location,
        public array $events,
        public ?string $hasNextLink,
        public int $current,
        public int $count,
    ) {
    }
}
