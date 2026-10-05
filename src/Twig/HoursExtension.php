<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Entity\Event;
use App\Entity\EventTimesheet;
use App\Utils\SessionHours;
use Twig\Attribute\AsTwigFunction;

/**
 * The hours of a session or an event, built from their times (SessionHours).
 */
final class HoursExtension
{
    #[AsTwigFunction(name: 'session_hours')]
    public function sessionHours(EventTimesheet $session): ?string
    {
        return SessionHours::ofSession($session);
    }

    #[AsTwigFunction(name: 'event_hours')]
    public function eventHours(Event $event): ?string
    {
        return SessionHours::ofEvent($event);
    }
}
