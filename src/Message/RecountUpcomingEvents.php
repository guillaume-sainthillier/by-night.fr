<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Message;

/**
 * Recounts the events to come of these venues, their cities and countries, after events of theirs were created,
 * changed or deleted on the site (see UpcomingEventCountListener).
 */
final readonly class RecountUpcomingEvents
{
    /**
     * @param list<int> $placeIds
     */
    public function __construct(
        public array $placeIds,
    ) {
    }
}
