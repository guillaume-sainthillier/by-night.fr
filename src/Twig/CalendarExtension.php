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
use App\Utils\GoogleCalendarLink;
use Twig\Attribute\AsTwigFunction;

final class CalendarExtension
{
    #[AsTwigFunction(name: 'google_calendar_url')]
    public function googleCalendarUrl(Event $event, string $eventUrl): ?string
    {
        return GoogleCalendarLink::forEvent($event, $eventUrl);
    }
}
