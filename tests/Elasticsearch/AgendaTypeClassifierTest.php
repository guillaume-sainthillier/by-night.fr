<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Elasticsearch;

use App\Elasticsearch\AgendaTypeClassifier;
use App\Elasticsearch\Message\RefreshEventDocuments;
use App\Entity\Event;
use App\Factory\EventFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function Zenstruck\Foundry\Persistence\refresh;

/**
 * What the nightly app:events:classify-agenda-types writes once Elasticsearch said which type pages list each event
 * to come (AgendaTypeClassifier::store()).
 */
final class AgendaTypeClassifierTest extends AppKernelTestCase
{
    private AgendaTypeClassifier $classifier;

    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = self::getContainer()->get(AgendaTypeClassifier::class);
        $this->today = new DateTimeImmutable('today');
    }

    public function testTheTypesFoundAreStoredAndTheEventsReindexed(): void
    {
        $concert = $this->event();
        $both = $this->event();
        $none = $this->event();

        self::assertSame(2, $this->classifier->store([$concert->getId() => ['concert'], $both->getId() => ['concert', 'family']], $this->today));

        self::assertSame(['concert'], refresh($concert)->getAgendaTypes());
        self::assertSame(['concert', 'family'], refresh($both)->getAgendaTypes());
        self::assertSame([], refresh($none)->getAgendaTypes());
        self::assertSame([[$concert->getId(), $both->getId()]], $this->reindexed());
    }

    public function testOnlyTheEventsWhoseTypesChangedAreWritten(): void
    {
        $unchanged = $this->event(['concert']);
        $changed = $this->event(['concert']);
        $lost = $this->event(['family']);

        self::assertSame(2, $this->classifier->store([$unchanged->getId() => ['concert'], $changed->getId() => ['show']], $this->today));

        self::assertSame(['show'], refresh($changed)->getAgendaTypes());
        self::assertSame([], refresh($lost)->getAgendaTypes(), 'No type page lists it anymore');
        self::assertSame([[$changed->getId(), $lost->getId()]], $this->reindexed());
        self::assertSame(0, $this->classifier->store([$unchanged->getId() => ['concert'], $changed->getId() => ['show']], $this->today));
    }

    public function testTheTypesOfPastEventsAreKept(): void
    {
        $past = EventFactory::new()->withDates(new DateTimeImmutable('-1 month'))->create(['agendaTypes' => ['concert']]);

        self::assertSame(0, $this->classifier->store([], $this->today));

        self::assertSame(['concert'], refresh($past)->getAgendaTypes());
    }

    public function testAnEventFoundAlthoughItEndedInTheDatabaseIsNotWrittenAgain(): void
    {
        // Its end date is over, but a session is still to come, so the agenda lists it
        $inconsistent = EventFactory::new()->withDates(new DateTimeImmutable('-1 month'))->create(['agendaTypes' => ['concert']]);

        self::assertSame(0, $this->classifier->store([$inconsistent->getId() => ['concert']], $this->today));
    }

    /**
     * @param list<string> $agendaTypes
     */
    private function event(array $agendaTypes = []): Event
    {
        return EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['agendaTypes' => $agendaTypes]);
    }

    /**
     * @return list<list<int>> the event ids of each re-index message sent
     */
    private function reindexed(): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');

        return array_map(static function (Envelope $envelope): array {
            $message = $envelope->getMessage();
            self::assertInstanceOf(RefreshEventDocuments::class, $message);

            return $message->eventIds;
        }, $transport->getSent());
    }
}
