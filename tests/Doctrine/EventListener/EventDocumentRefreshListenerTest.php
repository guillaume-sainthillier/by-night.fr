<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EventListener;

use App\Elasticsearch\Message\RefreshEventDocuments;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\save;

final class EventDocumentRefreshListenerTest extends AppKernelTestCase
{
    public function testAPlaceMovedToAnotherCityRefreshesItsEvents(): void
    {
        $place = PlaceFactory::createOne();
        $this->transport()->reset();

        $place->setCity(CityFactory::createOne(['country' => $place->getCountry()]));
        save($place);

        self::assertEquals([new RefreshEventDocuments(placeIds: [(int) $place->getId()])], $this->sentRefreshes());
    }

    public function testARenamedPlaceRefreshesItsEvents(): void
    {
        $place = PlaceFactory::createOne(['name' => 'Le Bikini']);
        $this->transport()->reset();

        $place->setName('Bikini');
        save($place);

        self::assertEquals([new RefreshEventDocuments(placeIds: [(int) $place->getId()])], $this->sentRefreshes());
    }

    public function testAPlaceChangeTheDocumentsDoNotReadRefreshesNothing(): void
    {
        $place = PlaceFactory::createOne(['slug' => 'le-bikini']);
        $this->transport()->reset();

        $place->setSlug('bikini');
        save($place);

        self::assertSame([], $this->sentRefreshes());
    }

    public function testARenamedTagRefreshesItsEvents(): void
    {
        $tag = TagFactory::createOne(['name' => 'Theatre']);
        $this->transport()->reset();

        $tag->setName('Théâtre');
        save($tag);

        self::assertEquals([new RefreshEventDocuments(tagIds: [(int) $tag->getId()])], $this->sentRefreshes());
    }

    public function testSessionsChangedWithoutTheirEventRefreshIt(): void
    {
        $timesheet = EventTimesheetFactory::createOne();
        $eventId = (int) $timesheet->getEvent()?->getId();
        $this->transport()->reset();

        $timesheet->setStartAt(new DateTimeImmutable('2026-12-24'))->setEndAt(new DateTimeImmutable('2026-12-24'));
        save($timesheet);
        self::assertEquals([new RefreshEventDocuments(eventIds: [$eventId])], $this->sentRefreshes());

        $this->transport()->reset();
        delete($timesheet);
        self::assertEquals([new RefreshEventDocuments(eventIds: [$eventId])], $this->sentRefreshes());
    }

    public function testSessionsChangedWithTheirEventAreLeftToTheIndexListener(): void
    {
        $timesheet = EventTimesheetFactory::createOne();
        $event = $timesheet->getEvent();
        self::assertNotNull($event);
        $this->transport()->reset();

        $event->setName('Renamed along with its sessions');
        $timesheet->setStartAt(new DateTimeImmutable('2026-12-24'))->setEndAt(new DateTimeImmutable('2026-12-24'));
        save($event);

        self::assertSame([], $this->sentRefreshes());
    }

    public function testNothingIsSentForANewEvent(): void
    {
        $this->transport()->reset();

        EventFactory::createOne();

        self::assertSame([], $this->sentRefreshes());
    }

    /**
     * @return list<RefreshEventDocuments>
     */
    private function sentRefreshes(): array
    {
        $messages = array_map(static fn ($envelope) => $envelope->getMessage(), $this->transport()->getSent());

        return array_values(array_filter($messages, static fn (object $message) => $message instanceof RefreshEventDocuments));
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
