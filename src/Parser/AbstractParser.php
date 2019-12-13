<?php

namespace App\Parser;

use App\Producer\EventProducer;
use Psr\Log\LoggerInterface;

abstract class AbstractParser implements ParserInterface
{
    /** @var EventProducer */
    private $eventProducer;

    /** @var LoggerInterface */
    private $logger;

    /** @var int */
    private $parsedEvents;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer)
    {
        $this->logger = $logger;
        $this->eventProducer = $eventProducer;
        $this->parsedEvents = 0;
    }

    public function publish(array $item): void
    {
        $item['from_data'] = static::getParserName();
        try {
            $this->eventProducer->scheduleEvent($item);
            ++$this->parsedEvents;
        } catch (\JsonException $e) {
            $this->logException($e, $item);
        }
    }

    public function getParsedEvents(): int
    {
        return $this->parsedEvents;
    }

    protected function logException(\Throwable $e, array $context = [])
    {
        $this->logger->error($e, $context);
    }
}
