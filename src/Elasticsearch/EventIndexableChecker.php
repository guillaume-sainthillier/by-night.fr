<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch;

use App\Entity\Event;

final readonly class EventIndexableChecker
{
    public function isIndexable(Event $event): bool
    {
        // Don't index duplicate events (they redirect anyway)
        // Also check if the event was already indexable (not a draft)
        return $event->isIndexable() && !$event->isDuplicate();
    }
}
