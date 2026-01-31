<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto\WidgetData;

use App\Entity\Event;

final readonly class TrendsWidgetData
{
    /**
     * @param array<Event>                             $trends
     * @param array{facebook: string, twitter: string} $shares
     */
    public function __construct(
        public Event $event,
        public bool $participate,
        public bool $interest,
        public array $trends,
        public int $count,
        public array $shares,
    ) {
    }
}
