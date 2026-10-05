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
 * What the nightly app:events:classify-agenda-types writes for each page of events to come, once Elasticsearch said
 * which type pages list each of them (AgendaTypeClassifier::store()).
 */
final class AgendaTypeClassifierTest extends AppKernelTestCase
{
    private AgendaTypeClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = self::getContainer()->get(AgendaTypeClassifier::class);
    }

    public function testTheTypesFoundAreStoredAndTheEventsReindexed(): void
    {
        $concert = $this->event();
        $both = $this->event();
        $none = $this->event();

        self::assertSame(2, $this->classifier->store([$concert->getId() => ['concert'], $both->getId() => ['concert', 'family'], $none->getId() => []]));

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
        $page = [$unchanged->getId() => ['concert'], $changed->getId() => ['show'], $lost->getId() => []];

        self::assertSame(2, $this->classifier->store($page));

        self::assertSame(['show'], refresh($changed)->getAgendaTypes());
        self::assertSame([], refresh($lost)->getAgendaTypes(), 'No type page lists it anymore');
        self::assertSame([[$changed->getId(), $lost->getId()]], $this->reindexed(), 'One message for the page');
        self::assertSame(0, $this->classifier->store($page));
    }

    public function testTheEventsOfOtherPagesAreLeftAsTheyAre(): void
    {
        $page = $this->event();
        $otherPage = $this->event(['family']);
        $past = EventFactory::new()->withDates(new DateTimeImmutable('-1 month'))->create(['agendaTypes' => ['concert']]);

        self::assertSame(1, $this->classifier->store([$page->getId() => ['concert']]));

        self::assertSame(['family'], refresh($otherPage)->getAgendaTypes());
        self::assertSame(['concert'], refresh($past)->getAgendaTypes());
        self::assertSame(0, $this->classifier->store([]), 'An empty page');
    }

    public function testAnEventFoundAlthoughItEndedInTheDatabaseIsNotWrittenAgain(): void
    {
        // Its end date is over, but a session is still to come, so the agenda lists it
        $inconsistent = EventFactory::new()->withDates(new DateTimeImmutable('-1 month'))->create(['agendaTypes' => ['concert']]);

        self::assertSame(0, $this->classifier->store([$inconsistent->getId() => ['concert']]));
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
