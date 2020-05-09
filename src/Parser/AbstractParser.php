<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser;

use App\Producer\EventProducer;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class AbstractParser implements ParserInterface
{
    private EventProducer $eventProducer;

    private LoggerInterface $logger;

    private int $parsedEvents;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer)
    {
        $this->logger = $logger;
        $this->eventProducer = $eventProducer;
        $this->parsedEvents = 0;
    }

    public function publish(array $item): void
    {
        $item['from_data'] = static::getParserName();
        $item['parser_version'] = static::getParserVersion();
        try {
            $this->eventProducer->scheduleEvent($item);
            ++$this->parsedEvents;
        } catch (JsonException $e) {
            $this->logException($e, ['item' => $item]);
        }
    }

    public function getParsedEvents(): int
    {
        return $this->parsedEvents;
    }

    protected function logException(Throwable $exception, array $context = [])
    {
        $this->logger->error($exception->getMessage(), [
            'exception' => $exception,
            'extra' => $context
        ]);
    }

    public static function getParserVersion(): string
    {
        return '1.0';
    }
}
