<?php

namespace App\Producer;

use OldSound\RabbitMqBundle\RabbitMq\Producer;

class EventProducer extends Producer
{
    public function scheduleEvent(array $event): void
    {
        $this->publish(json_encode($event, \JSON_THROW_ON_ERROR));
    }
}
