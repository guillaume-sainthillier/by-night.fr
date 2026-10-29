<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EventListener;

use App\Enum\EventStatus;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

use function Zenstruck\Foundry\Persistence\save;

final class OutingChangeListenerTest extends AppKernelTestCase
{
    use ClockSensitiveTrait;

    public function testAChangeOfDatesVenueOrStatusFlagsTheEvent(): void
    {
        $dated = EventFactory::createOne();
        $moved = EventFactory::createOne();
        $cancelled = EventFactory::createOne();
        self::mockTime('2026-10-24 10:00');

        // A day after the one the factory drew: a date of its own could be that same day, which changes nothing
        $dated->setStartDate(DateTimeImmutable::createFromInterface($dated->getStartDate())->modify('+1 day'));
        save($dated);
        $moved->setPlace(PlaceFactory::createOne());
        save($moved);
        $cancelled->setStatus(EventStatus::Cancelled);
        save($cancelled);

        foreach ([$dated, $moved, $cancelled] as $event) {
            self::assertEquals(new DateTimeImmutable('2026-10-24 10:00'), EventFactory::find(['id' => $event->getId()])->getFlaggedForAlertAt());
        }
    }

    public function testEachChangeMovesTheFlagToItsTime(): void
    {
        $event = EventFactory::createOne();
        self::mockTime('2026-10-24 10:00');
        $event->setStatus(EventStatus::Postponed);
        save($event);

        self::mockTime('2026-10-24 10:30');
        $event->setStatus(EventStatus::Cancelled);
        save($event);

        self::assertEquals(new DateTimeImmutable('2026-10-24 10:30'), EventFactory::find(['id' => $event->getId()])->getFlaggedForAlertAt());
    }

    public function testHidingTheEventOrPublishingItAgainFlagsIt(): void
    {
        $event = EventFactory::createOne();

        $event->setDraft(true);
        save($event);

        self::assertNotNull(EventFactory::find(['id' => $event->getId()])->getFlaggedForAlertAt());
    }

    public function testAnotherChangeFlagsNothing(): void
    {
        $event = EventFactory::createOne();

        $event->setName('Nouveau nom');
        save($event);

        self::assertNull(EventFactory::find(['id' => $event->getId()])->getFlaggedForAlertAt());
    }

    public function testADuplicateIsNotFlagged(): void
    {
        $duplicate = EventFactory::createOne(['duplicateOf' => EventFactory::createOne()]);

        $duplicate->setStatus(EventStatus::Cancelled);
        save($duplicate);

        self::assertNull(EventFactory::find(['id' => $duplicate->getId()])->getFlaggedForAlertAt());
    }
}
