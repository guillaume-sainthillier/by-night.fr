<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EventListener;

use App\Entity\Event;
use App\Factory\EventFactory;
use App\Factory\UserEventFactory;
use App\Tests\AppKernelTestCase;
use Doctrine\DBAL\Connection;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\save;

final class UserEventCountListenerTest extends AppKernelTestCase
{
    public function testANewCalendarEntryRecountsItsEvent(): void
    {
        $event = EventFactory::createOne(['participations' => 7, 'interests' => 3]);
        UserEventFactory::createOne(['event' => $event, 'going' => true]);
        UserEventFactory::createOne(['event' => $event, 'going' => false, 'wish' => true]);

        self::assertSame([1, 1], [$event->getParticipations(), $event->getInterests()]);
        self::assertSame([1, 1], $this->storedCounts($event));
    }

    public function testNoLongerGoingIsNoLongerCounted(): void
    {
        $userEvent = UserEventFactory::createOne(['going' => true]);
        $event = $userEvent->getEvent();

        $userEvent->setGoing(false);
        save($userEvent);

        self::assertSame(0, $event->getParticipations());
        self::assertSame([0, 0], $this->storedCounts($event));
    }

    public function testACalendarEntryMovedToAnotherEventRecountsBoth(): void
    {
        $userEvent = UserEventFactory::createOne(['going' => true]);
        $from = $userEvent->getEvent();
        $to = EventFactory::createOne();

        $userEvent->setEvent($to);
        save($userEvent);

        self::assertSame([[0, 0], [1, 0]], [$this->storedCounts($from), $this->storedCounts($to)]);
    }

    public function testADeletedCalendarEntryIsNoLongerCounted(): void
    {
        $userEvent = UserEventFactory::createOne(['going' => true]);
        $event = $userEvent->getEvent();

        delete($userEvent);

        self::assertSame(0, $event->getParticipations());
        self::assertSame([0, 0], $this->storedCounts($event));
    }

    /**
     * @return array{int, int} the participations and interests in the database
     */
    private function storedCounts(Event $event): array
    {
        $connection = self::getContainer()->get(Connection::class);
        $row = $connection->fetchAssociative('SELECT participations, interests FROM `event` WHERE id = ?', [$event->getId()]);
        self::assertIsArray($row);

        return [(int) $row['participations'], (int) $row['interests']];
    }
}
