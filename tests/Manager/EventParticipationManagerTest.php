<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Manager;

use App\Enum\EventStatus;
use App\Factory\EventFactory;
use App\Factory\PublisherFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use App\Manager\EventParticipationManager;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

use function Zenstruck\Foundry\Persistence\refresh;

final class EventParticipationManagerTest extends AppKernelTestCase
{
    /**
     * The counters are recounted from the calendars, not moved by one: a stale count is corrected on the next change.
     */
    public function testTakingPartRecountsTheEvent(): void
    {
        $event = EventFactory::createOne(['participations' => 7, 'interests' => 3]);
        UserEventFactory::createOne(['event' => $event, 'going' => true]);
        UserEventFactory::createOne(['event' => $event, 'going' => false, 'wish' => true]);

        $this->getManager()->participate(UserFactory::createOne(), $event, true);

        refresh($event);
        self::assertSame(2, $event->getParticipations());
        self::assertSame(1, $event->getInterests());
    }

    /**
     * Leaving keeps the event in the member's calendar, not going: their participation no longer counts.
     */
    public function testLeavingKeepsTheEventInTheCalendar(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne();
        $this->getManager()->participate($user, $event, true);

        $this->getManager()->participate($user, $event, false);

        refresh($event);
        self::assertSame(0, $event->getParticipations());
        self::assertSame(1, UserEventFactory::count(['user' => $user, 'event' => $event, 'going' => false]));
    }

    /**
     * What the member is told changed starts from the event as they saw it when they said "J'y vais".
     */
    public function testGoingRecordsWhatTheMemberKnowsOfTheEvent(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::new()->withDates(new DateTimeImmutable('2026-12-05'))->create(['status' => EventStatus::SoldOut]);

        $this->getManager()->participate($user, $event, true);

        $known = UserEventFactory::find(['user' => $user, 'event' => $event])->getKnownState();
        self::assertNotNull($known);
        self::assertSame('2026-12-05', $known->start);
        self::assertSame('2026-12-05', $known->end);
        self::assertSame($event->getPlace()?->getId(), $known->place);
        self::assertSame('sold_out', $known->status);
    }

    /**
     * Publishing an event says "J'y vais" for its author, who is never told the changes they make.
     */
    public function testTheAuthorGoingRecordsNothing(): void
    {
        $author = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $author]);

        $this->getManager()->participate($author, $event, true);

        self::assertNull(UserEventFactory::find(['user' => $author, 'event' => $event])->getKnownState());
    }

    public function testTheMemberManagingThePublisherGoingRecordsNothing(): void
    {
        $manager = UserFactory::createOne();
        $event = EventFactory::createOne(['publisher' => PublisherFactory::createOne(['owner' => $manager])]);

        $this->getManager()->participate($manager, $event, true);

        self::assertNull(UserEventFactory::find(['user' => $manager, 'event' => $event])->getKnownState());
    }

    public function testAnEventNobodyFollowsCountsNobody(): void
    {
        $followed = EventFactory::createOne(['participations' => 5]);
        $forgotten = EventFactory::createOne(['participations' => 4, 'interests' => 2]);
        UserEventFactory::createMany(3, ['event' => $followed, 'going' => true]);

        $this->getManager()->recount([$followed, $forgotten]);

        self::assertSame(3, $followed->getParticipations());
        self::assertSame(0, $forgotten->getParticipations());
        self::assertSame(0, $forgotten->getInterests());
    }

    private function getManager(): EventParticipationManager
    {
        return self::getContainer()->get(EventParticipationManager::class);
    }
}
