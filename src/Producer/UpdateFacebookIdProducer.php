<?php

namespace App\Producer;

use OldSound\RabbitMqBundle\RabbitMq\Producer;

class UpdateFacebookIdProducer extends Producer
{
    public function scheduleUpdate($oldValue, $newValue): void
    {
        $this->publish(json_encode([
            'old' => $oldValue,
            'new' => $newValue,
        ]));
    }
}
