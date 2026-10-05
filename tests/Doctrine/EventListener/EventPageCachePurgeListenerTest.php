<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EventListener;

use App\Factory\CommentFactory;
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use App\Message\PurgeCdnCacheTags;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\flush_after;
use function Zenstruck\Foundry\Persistence\save;

final class EventPageCachePurgeListenerTest extends AppKernelTestCase
{
    public function testAnEditedEventPurgesItsPage(): void
    {
        $event = EventFactory::createOne(['name' => 'Concert']);
        $this->transport()->reset();

        $event->setName('Concert annulé');
        save($event);

        self::assertSame(['event-' . $event->getId()], $this->purgedTags());
    }

    public function testAParserVersionBumpPurgesNothing(): void
    {
        $event = EventFactory::createOne(['parserVersion' => '1.0']);
        $this->transport()->reset();

        $event->setParserVersion('1.1');
        save($event);

        self::assertSame([], $this->purgedTags(), 'the page does not show it');
    }

    public function testADeletedEventPurgesItsPage(): void
    {
        $event = EventFactory::createOne();
        $tag = 'event-' . $event->getId();
        $this->transport()->reset();

        delete($event);

        self::assertContains($tag, $this->purgedTags());
    }

    public function testANewSessionPurgesItsEventsPage(): void
    {
        $event = EventFactory::createOne();
        $this->transport()->reset();

        EventTimesheetFactory::createOne(['event' => $event]);

        self::assertSame(['event-' . $event->getId()], $this->purgedTags());
    }

    public function testAChangedSessionPurgesItsEventsPage(): void
    {
        $timesheet = EventTimesheetFactory::createOne();
        $this->transport()->reset();

        $timesheet->setStartAt(new DateTimeImmutable('2026-12-24'))->setEndAt(new DateTimeImmutable('2026-12-24'));
        save($timesheet);

        self::assertSame(['event-' . $timesheet->getEvent()?->getId()], $this->purgedTags());
    }

    public function testACommentPurgesItsEventsPage(): void
    {
        $event = EventFactory::createOne();
        $this->transport()->reset();

        CommentFactory::createOne(['event' => $event]);

        self::assertContains('event-' . $event->getId(), $this->purgedTags());
    }

    public function testAParticipantPurgesItsEventsPage(): void
    {
        $event = EventFactory::createOne();
        $this->transport()->reset();

        UserEventFactory::createOne(['event' => $event]);

        self::assertContains('event-' . $event->getId(), $this->purgedTags());
    }

    public function testAMovedPlacePurgesTheEventPagesShowingIt(): void
    {
        $place = PlaceFactory::createOne(['name' => 'Le Bikini']);
        $this->transport()->reset();

        $place->setName('Bikini');
        save($place);

        self::assertSame(['place-' . $place->getId()], $this->purgedTags());
    }

    public function testAPlaceChangeTheEventPageDoesNotShowPurgesNothing(): void
    {
        $place = PlaceFactory::createOne(['slug' => 'le-bikini']);
        $this->transport()->reset();

        $place->setSlug('bikini');
        save($place);

        self::assertSame([], $this->purgedTags());
    }

    public function testPurgesDoNotQueueBehindTheSearchIndexUpdates(): void
    {
        $event = EventFactory::createOne(['name' => 'Concert']);
        $async = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $async);
        $async->reset();

        $event->setName('Concert annulé');
        save($event);

        self::assertNotSame([], $this->purgedTags());
        foreach ($async->getSent() as $envelope) {
            self::assertNotInstanceOf(PurgeCdnCacheTags::class, $envelope->getMessage(), 'the Cloudflare throttle would hold up the "async" worker');
        }
    }

    public function testAFlushQueuesItsTagsByRequest(): void
    {
        $place = PlaceFactory::createOne(['name' => 'Le Bikini']);
        // One category and no themes: 150 events would exhaust the unique tag names
        $events = EventFactory::createMany(150, ['place' => $place, 'category' => TagFactory::createOne(), 'themes' => [], 'user' => UserFactory::createOne()]);
        $this->transport()->reset();

        flush_after(static function () use ($place, $events): void {
            $place->setName('Bikini');
            save($place);
            foreach ($events as $event) {
                $event->setName('Renamed');
                save($event);
                // Its session in the same flush: the same page, purged once
                EventTimesheetFactory::createOne(['event' => $event]);
            }
        });

        self::assertSame([100, 51], array_map(\count(...), $this->sentTagLists()), 'one message per Cloudflare request');
        self::assertCount(151, array_unique($this->purgedTags()));
    }

    public function testANewEventPurgesNothing(): void
    {
        $this->transport()->reset();

        EventFactory::createOne();

        self::assertSame([], $this->purgedTags());
    }

    /**
     * @return list<string>
     *
     * @phpstan-impure
     */
    private function purgedTags(): array
    {
        return array_merge(...$this->sentTagLists());
    }

    /**
     * @return list<list<string>> the tags of each message
     *
     * @phpstan-impure
     */
    private function sentTagLists(): array
    {
        $lists = [];
        foreach ($this->transport()->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof PurgeCdnCacheTags) {
                $lists[] = $message->tags;
            }
        }

        return $lists;
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.cdn');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
