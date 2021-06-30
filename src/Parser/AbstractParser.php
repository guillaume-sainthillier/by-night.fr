<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser;

use App\Contracts\ParserInterface;
use App\Dto\EventDto;
use App\Handler\ReservationsHandler;
use App\Producer\EventProducer;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class AbstractParser implements ParserInterface
{
    protected ReservationsHandler $reservationsHandler;
    private EventProducer $eventProducer;
    private LoggerInterface $logger;
    private int $parsedEvents = 0;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer, ReservationsHandler $reservationsHandler)
    {
        $this->logger = $logger;
        $this->eventProducer = $eventProducer;
        $this->reservationsHandler = $reservationsHandler;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return sprintf('%s v%s', static::getParserName(), static::getParserVersion());
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserVersion(): string
    {
        return '1.0';
    }

    /**
     * {@inheritDoc}
     */
    public function publish(EventDto $eventDto): void
    {
        $eventDto->parserName = static::getParserName();
        $eventDto->parserVersion = static::getParserVersion();

        $this->eventProducer->scheduleEvent($eventDto);
        ++$this->parsedEvents;
    }

    protected function logException(Throwable $exception, array $context = []): void
    {
        $this->logger->error($exception->getMessage(), [
            'exception' => $exception,
            'extra' => $context,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getParsedEvents(): int
    {
        return $this->parsedEvents;
    }
}
