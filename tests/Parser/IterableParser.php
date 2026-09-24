<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Parser;

use App\Dto\EventDto;
use App\Handler\EventHandler;
use App\Parser\AbstractParser;
use Closure;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A parser whose source is the events it is given, for AbstractParserTest.
 */
final class IterableParser extends AbstractParser
{
    /**
     * @param Closure(): iterable<EventDto|null> $events
     */
    public function __construct(
        LoggerInterface $logger,
        MessageBusInterface $messageBus,
        EventHandler $eventHandler,
        private readonly Closure $events,
    ) {
        parent::__construct($logger, $messageBus, $eventHandler);
    }

    public static function getParserName(): string
    {
        return 'Iterable';
    }

    public function getCommandName(): string
    {
        return 'test.feed';
    }

    protected function fetchEvents(?DateTimeImmutable $since): iterable
    {
        return ($this->events)();
    }

    /**
     * Gives an event the parser name, version and origin publish() would, so its content
     * hash can be stored beforehand as the previous run's.
     */
    public function stamp(EventDto $eventDto): void
    {
        $eventDto->parserName = self::getParserName();
        $eventDto->parserVersion = self::getParserVersion();
        $eventDto->externalOrigin = $this->getCommandName();
    }
}
