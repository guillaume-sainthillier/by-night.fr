<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EventListener;

use App\Entity\Place;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Message\RecountUpcomingEvents;
use App\Tests\AppKernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\flush_after;
use function Zenstruck\Foundry\Persistence\save;

final class UpcomingEventCountListenerTest extends AppKernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // As on the site: the personal space or the back office
        $requestStack = self::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push(Request::create('/'));
    }

    public function testANewEventRecountsItsVenue(): void
    {
        $place = PlaceFactory::createOne();
        $this->transport()->reset();

        EventFactory::createOne(['place' => $place]);

        self::assertEquals([new RecountUpcomingEvents([self::id($place)])], $this->sentRecounts());
    }

    public function testANewEventAndItsNewVenueRecountTheVenueOnceItHasAnId(): void
    {
        $this->transport()->reset();

        $event = EventFactory::createOne();

        self::assertEquals([new RecountUpcomingEvents([self::id($event->getPlace())])], $this->sentRecounts());
    }

    public function testAnEventMovedToAnotherVenueRecountsBothVenues(): void
    {
        $event = EventFactory::createOne();
        $from = $event->getPlace();
        $to = PlaceFactory::createOne();
        $this->transport()->reset();

        $event->setPlace($to);
        save($event);

        self::assertEquals([new RecountUpcomingEvents([self::id($from), self::id($to)])], $this->sentRecounts());
    }

    public function testAnEventSavedAsDraftRecountsItsVenue(): void
    {
        $event = EventFactory::createOne();
        $this->transport()->reset();

        $event->setDraft(true);
        save($event);

        self::assertEquals([new RecountUpcomingEvents([self::id($event->getPlace())])], $this->sentRecounts());
    }

    public function testAChangeThatDoesNotMoveTheCountsRecountsNothing(): void
    {
        $event = EventFactory::createOne();
        $this->transport()->reset();

        $event->setName('Renamed');
        save($event);

        self::assertSame([], $this->sentRecounts());
    }

    public function testDeletedEventsRecountTheirVenuesOnce(): void
    {
        $place = PlaceFactory::createOne();
        $events = EventFactory::createMany(2, ['place' => $place]);
        $this->transport()->reset();

        flush_after(static function () use ($events): void {
            foreach ($events as $event) {
                delete($event);
            }
        });

        self::assertEquals([new RecountUpcomingEvents([self::id($place)])], $this->sentRecounts());
    }

    public function testChangesOutsideARequestAreLeftToTheNightlyCount(): void
    {
        $requestStack = self::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->pop();
        $this->transport()->reset();

        $event = EventFactory::createOne();
        $event->setDraft(true);
        save($event);
        delete($event);

        self::assertSame([], $this->sentRecounts());
    }

    private static function id(?Place $place): int
    {
        self::assertNotNull($place?->getId());

        return $place->getId();
    }

    /**
     * @return list<RecountUpcomingEvents>
     */
    private function sentRecounts(): array
    {
        $messages = array_map(static fn ($envelope) => $envelope->getMessage(), $this->transport()->getSent());

        return array_values(array_filter($messages, static fn (object $message) => $message instanceof RecountUpcomingEvents));
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
