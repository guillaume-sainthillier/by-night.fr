<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Producer;

use const JSON_THROW_ON_ERROR;
use JsonException;
use OldSound\RabbitMqBundle\RabbitMq\Producer;

class EventProducer extends Producer
{
    /**
     * @throws JsonException
     */
    public function scheduleEvent(array $event): void
    {
        $this->publish(json_encode($event, JSON_THROW_ON_ERROR));
    }
}
