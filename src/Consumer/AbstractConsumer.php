<?php

namespace App\Consumer;

use Psr\Log\LoggerInterface;

abstract class AbstractConsumer
{
    /** @var LoggerInterface */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
