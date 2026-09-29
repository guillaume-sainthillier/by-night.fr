<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Elasticsearch\Message\RefreshEventDocuments;
use App\Entity\Event;
use App\Factory\EventFactory;
use App\Tests\AppKernelTestCase;
use Elastica\Index;
use Elastica\Mapping;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Zenstruck\Foundry\Object\Instantiator;

use function Zenstruck\Foundry\Persistence\refresh;

/**
 * The events stored before Event::$startingPrice get it from their prices; the ones to come are re-indexed.
 */
final class EventsBackfillStartingPricesCommandTest extends AppKernelTestCase
{
    public function testTheStartingPriceIsReadFromThePricesOfTheEventsStoredWithout(): void
    {
        // Replaced before any service reads it
        $index = $this->createMock(Index::class);
        $index->expects(self::once())->method('setMapping')->with(new Mapping(['startingPrice' => ['type' => 'float']]));
        self::getContainer()->set('fos_elastica.index.event', $index);

        $paying = $this->storedWithout('De 15€ à 25€');
        $free = $this->storedWithout('Entrée libre');
        $past = $this->storedWithout('12€', past: true);
        $unknown = $this->storedWithout('Sur inscription');
        $current = EventFactory::createOne(['prices' => '39€']);

        $display = $this->backfill();

        self::assertSame(15.0, refresh($paying)->getStartingPrice());
        self::assertSame(0.0, refresh($free)->getStartingPrice(), 'Free, not unknown');
        self::assertSame(12.0, refresh($past)->getStartingPrice());
        self::assertNull(refresh($unknown)->getStartingPrice());
        self::assertSame(39.0, refresh($current)->getStartingPrice());
        self::assertSame([[$paying->getId(), $free->getId()]], $this->reindexed(), 'The past events are not in the index');
        self::assertStringContainsString('Starting price of 3 events updated, 2 of them to come', $display);
    }

    public function testADryRunWritesNothing(): void
    {
        $event = $this->storedWithout('22€');

        $display = $this->backfill(['--dry-run' => true]);

        self::assertNull(refresh($event)->getStartingPrice());
        self::assertSame([], $this->reindexed());
        self::assertStringContainsString('1 of 1 events would change, 1 of them to come', $display);
    }

    /**
     * An event as stored before the column: its prices set, not through Event::setPrices().
     */
    private function storedWithout(string $prices, bool $past = false): Event
    {
        $factory = EventFactory::new()->instantiateWith(Instantiator::withConstructor()->alwaysForce('prices'));

        return ($past ? $factory->past() : $factory->upcoming())->create(['prices' => $prices]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function backfill(array $options = []): string
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:events:backfill-starting-prices'));
        $tester->execute($options);
        $tester->assertCommandIsSuccessful();

        return $tester->getDisplay(true);
    }

    /**
     * @return list<list<int>> the ids of each re-indexing sent
     */
    private function reindexed(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        $messages = array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $transport->getSent());

        return array_values(array_map(
            static fn (RefreshEventDocuments $message): array => $message->eventIds,
            array_filter($messages, static fn (object $message): bool => $message instanceof RefreshEventDocuments),
        ));
    }
}
