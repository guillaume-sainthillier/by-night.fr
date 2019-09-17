<?php

namespace App\Producer;

use OldSound\RabbitMqBundle\RabbitMq\Producer;

class RemoveImageThumbnailsProducer extends Producer
{
    public function scheduleRemove(array $path): void
    {
        $this->publish(json_encode($path));
    }
}
