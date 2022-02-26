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
    private int $parsedEvents = 0;

    public function __construct(private LoggerInterface $logger, private EventProducer $eventProducer, protected ReservationsHandler $reservationsHandler)
    {
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
        $eventDto->externalOrigin = $this->getCommandName();

        if (null !== $eventDto->place) {
            $eventDto->place->externalOrigin = $eventDto->externalOrigin;
        }

        $this->sanitize($eventDto);
        $this->eventProducer->scheduleEvent($eventDto);
        ++$this->parsedEvents;
    }

    /**
     * {@inheritDoc}
     */
    public function getParsedEvents(): int
    {
        return $this->parsedEvents;
    }

    protected function logException(Throwable $exception, array $context = []): void
    {
        $this->logger->error($exception->getMessage(), [
            'exception' => $exception,
            'extra' => $context,
        ]);
    }

    private function sanitize(object $object): void
    {
        foreach ($object as $key => $value) {
            $object->{$key} = $this->getSanitizedValue($value);
        }
    }

    private function getSanitizedValue($value)
    {
        if (\is_object($value)) {
            $this->sanitize($value);
        } elseif (\is_array($value)) {
            foreach ($value as $key => $itemValue) {
                $itemValue = $this->getSanitizedValue($itemValue);
                if (null !== $itemValue) {
                    $value[$key] = $itemValue;
                } else {
                    unset($value[$key]);
                }
            }
        } elseif (\is_string($value) && '' === trim($value)) {
            $value = null;
        }

        return $value;
    }
}
