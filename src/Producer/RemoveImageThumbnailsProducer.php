<?php

namespace App\Producer;

use OldSound\RabbitMqBundle\RabbitMq\Producer;

class RemoveImageThumbnailsProducer extends Producer
{
    public function scheduleRemove(string $path): void
    {
        $this->publish($path);
    }
}
