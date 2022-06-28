<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Producer;

use App\Dto\EventDto;
use OldSound\RabbitMqBundle\RabbitMq\Producer;

class EventProducer extends Producer
{
    public function scheduleEvent(EventDto $event): void
    {
        $this->publish(serialize($event));
    }
}
