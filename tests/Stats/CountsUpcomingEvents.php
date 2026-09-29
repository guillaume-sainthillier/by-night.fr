<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Stats;

use App\Repository\EventRepository;
use App\Stats\UpcomingEventCounter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * What the nightly app:events:count-upcoming does, for the tests of the pages that read the stored counts. Built by
 * hand: the counter is a private service, only injected into the command and RecountUpcomingEventsHandler.
 */
trait CountsUpcomingEvents
{
    private static function counter(): UpcomingEventCounter
    {
        return new UpcomingEventCounter(
            self::getContainer()->get(EntityManagerInterface::class),
            self::getContainer()->get(EventRepository::class),
        );
    }
}
