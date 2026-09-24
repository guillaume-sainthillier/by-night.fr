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
use App\Factory\ParserDataFactory;
use App\Handler\EventHandler;
use App\Import\EventContentHasher;
use App\Import\EventPublicationGuard;
use App\Import\Firewall;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class AbstractParserTest extends AppKernelTestCase
{
    /**
     * @var list<EventDto>
     */
    private array $dispatched = [];

    public function testPublishManyChecksEachChunkWithASingleLookup(): void
    {
        // Already imported and unchanged: the gate must hold it back
        $known = self::event('known');
        $this->parser(static fn (): iterable => [])->stamp($known);
        // Hashed as publish() hashes it: after cleaning
        self::getContainer()->get(EventHandler::class)->cleanEvent($known);
        ParserDataFactory::createOne([
            'externalId' => 'known',
            'externalOrigin' => 'test.feed',
            'firewallVersion' => Firewall::VERSION,
            'parserVersion' => '1.0',
            'contentHash' => new EventContentHasher()->hash($known),
        ]);

        $events = static function (): iterable {
            yield self::event('known');
            yield null; // a row the parser could not map
            for ($i = 1; $i <= 501; ++$i) {
                yield self::event(\sprintf('new-%d', $i));
            }
        };

        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        self::assertInstanceOf(BacktraceDebugDataHolder::class, $queries);
        $queries->reset();

        $parser = $this->parser($events);
        $parser->parse(null);

        self::assertSame(501, $parser->getParsedEvents());
        self::assertSame(1, $parser->getSkippedEvents());
        self::assertCount(501, $this->dispatched);
        self::assertNotContains('known', array_map(static fn (EventDto $dto): ?string => $dto->externalId, $this->dispatched));

        $lookups = array_filter(
            array_column($queries->getData()['default'] ?? [], 'sql'),
            static fn (string $sql): bool => str_contains($sql, 'FROM parser_data'),
        );
        self::assertCount(2, $lookups, '502 events are two chunks of at most 500: one lookup each, none per event');
    }

    private static function event(string $externalId): EventDto
    {
        $event = new EventDto();
        $event->externalId = $externalId;
        $event->name = 'Concert';
        $event->description = 'A nice concert in town';
        $event->startDate = new DateTimeImmutable('2026-10-01');
        $event->endDate = new DateTimeImmutable('2026-10-01');

        return $event;
    }

    /**
     * @param callable(): iterable<EventDto|null> $events
     */
    private function parser(callable $events): IterableParser
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            self::assertInstanceOf(EventDto::class, $message);
            $this->dispatched[] = $message;

            return new Envelope($message);
        });

        $parser = new IterableParser(new NullLogger(), $bus, self::getContainer()->get(EventHandler::class), $events(...));
        $parser->setPublicationGuard(self::getContainer()->get(EventPublicationGuard::class));

        return $parser;
    }
}
