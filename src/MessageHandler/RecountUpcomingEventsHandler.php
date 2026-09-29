<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Message\RecountUpcomingEvents;
use App\Stats\UpcomingEventCounter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecountUpcomingEventsHandler
{
    public function __construct(
        private UpcomingEventCounter $counter,
    ) {
    }

    public function __invoke(RecountUpcomingEvents $message): void
    {
        $this->counter->refreshPlaces($message->placeIds);
    }
}
