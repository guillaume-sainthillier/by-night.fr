<?php

namespace App\Producer;

use OldSound\RabbitMqBundle\RabbitMq\Producer;

class PurgeCdnCacheUrlProducer extends Producer
{
    public function schedulePurge(string $path): void
    {
        $this->publish($path);
    }
}
