<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\MessageHandler;

use App\Entity\Event;
use App\Enum\EventStatus;
use App\Factory\EventFactory;
use App\Factory\ParserDataFactory;
use App\Message\RemoveSourceEvents;
use App\MessageHandler\RemoveSourceEventsHandler;
use App\Tests\AppKernelTestCase;

use function Zenstruck\Foundry\Persistence\save;

final class RemoveSourceEventsHandlerTest extends AppKernelTestCase
{
    public function testAnEventItsSourceNoLongerListsLeavesTheListingsButKeepsItsPage(): void
    {
        $id = $this->imported('oa-1', 'https://openagenda.com/agenda-42/events/concert')->getId();

        $this->handle(new RemoveSourceEvents('openagenda', ['oa-1']));

        $event = EventFactory::find(['id' => $id]);
        self::assertSame(EventStatus::Removed, $event->getStatus());
        self::assertNull($event->getStatusMessage(), 'The source\'s former message no longer applies');
        self::assertTrue($event->isDraft(), 'Out of the listings');
        self::assertFalse($event->isIndexable());
    }

    public function testARemovedCancelledEventIsRemoved(): void
    {
        $event = $this->imported('oa-1');
        save($event->setStatus(EventStatus::Cancelled)->setStatusMessage('Annulé pour raisons de santé'));

        $this->handle(new RemoveSourceEvents('openagenda', ['oa-1']));

        $event = EventFactory::find(['id' => $event->getId()]);
        self::assertSame(EventStatus::Removed, $event->getStatus(), 'Removed overrides Cancelled');
        self::assertTrue($event->isDraft());
    }

    public function testAnEventOverIsRemovedToo(): void
    {
        $id = EventFactory::new()->past()->create([
            'user' => null,
            'externalId' => 'oa-1',
            'externalOrigin' => 'openagenda',
        ])->getId();

        $this->handle(new RemoveSourceEvents('openagenda', ['oa-1']));

        self::assertSame(EventStatus::Removed, EventFactory::find(['id' => $id])->getStatus());
    }

    public function testAMemberEventIsLeftAlone(): void
    {
        // A member's event never comes from a source: only an inconsistent row could match a tombstone
        $id = EventFactory::new()->upcoming()->create([
            'externalId' => 'oa-1',
            'externalOrigin' => 'openagenda',
        ])->getId();

        $this->handle(new RemoveSourceEvents('openagenda', ['oa-1']));

        $event = EventFactory::find(['id' => $id]);
        self::assertNull($event->getStatus());
        self::assertFalse($event->isDraft());
    }

    public function testTheNextListingOfTheRecordReachesTheImportWhateverItsContent(): void
    {
        $this->imported('oa-1');
        ParserDataFactory::createOne(['externalId' => 'oa-1', 'externalOrigin' => 'openagenda']);
        ParserDataFactory::createOne(['externalId' => 'oa-2', 'externalOrigin' => 'openagenda']);

        $this->handle(new RemoveSourceEvents('openagenda', ['oa-1']));

        self::assertNull(ParserDataFactory::find(['externalId' => 'oa-1'])->getContentHash(), 'Else the dedup gate drops it as unchanged');
        self::assertNotNull(ParserDataFactory::find(['externalId' => 'oa-2'])->getContentHash());
    }

    public function testOnlyTheRecordsOfThatSourceAreRemoved(): void
    {
        $sameIdElsewhere = $this->imported('oa-1', origin: 'sowprog')->getId();
        $stillListed = $this->imported('oa-2')->getId();

        $this->handle(new RemoveSourceEvents('openagenda', ['oa-1']));

        self::assertNull(EventFactory::find(['id' => $sameIdElsewhere])->getStatus());
        self::assertNull(EventFactory::find(['id' => $stillListed])->getStatus());
    }

    public function testAnEventRemovedFromAnotherAgendaThanItsOwnStays(): void
    {
        $id = $this->imported('oa-1', 'https://openagenda.com/agenda-42/events/concert')->getId();

        $this->handle(new RemoveSourceEvents('openagenda', ['oa-1'], 'https://openagenda.com/agenda-7/events/'));

        self::assertNull(EventFactory::find(['id' => $id])->getStatus(), 'Still listed on the agenda it was imported from');
    }

    private function imported(string $externalId, ?string $source = null, string $origin = 'openagenda'): Event
    {
        return EventFactory::new()->upcoming()->create([
            'user' => null,
            'externalId' => $externalId,
            'externalOrigin' => $origin,
            'source' => $source,
        ]);
    }

    private function handle(RemoveSourceEvents $message): void
    {
        self::getContainer()->get(RemoveSourceEventsHandler::class)($message);
    }
}
