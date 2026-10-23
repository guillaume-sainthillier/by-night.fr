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
use App\Message\CheckOutingChanges;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function Zenstruck\Foundry\Persistence\save;

final class OutingChangeListenerTest extends AppKernelTestCase
{
    public function testAChangeOfDatesVenueOrStatusIsCheckedOnceSettled(): void
    {
        $dated = EventFactory::createOne();
        $moved = EventFactory::createOne();
        $cancelled = EventFactory::createOne();
        $this->transport()->reset();

        $dated->setStartDate(new DateTimeImmutable('+10 days'));
        save($dated);
        $moved->setPlace(PlaceFactory::createOne());
        save($moved);
        $cancelled->setStatus(EventStatus::Cancelled);
        save($cancelled);

        self::assertEqualsCanonicalizing([$dated->getId(), $moved->getId(), $cancelled->getId()], $this->checkedEventIds());
        foreach ($this->transport()->getSent() as $envelope) {
            if ($envelope->getMessage() instanceof CheckOutingChanges) {
                self::assertSame(15 * 60 * 1000, $envelope->last(DelayStamp::class)?->getDelay());
            }
        }
    }

    public function testAnotherChangeIsNotChecked(): void
    {
        $event = EventFactory::createOne();
        $this->transport()->reset();

        $event->setName('Nouveau nom');
        save($event);

        self::assertSame([], $this->checkedEventIds());
    }

    public function testADuplicateIsNotChecked(): void
    {
        $duplicate = EventFactory::createOne(['duplicateOf' => EventFactory::createOne()]);
        $this->transport()->reset();

        $duplicate->setStatus(EventStatus::Cancelled);
        save($duplicate);

        self::assertSame([], $this->checkedEventIds());
    }

    /**
     * @return list<int>
     */
    private function checkedEventIds(): array
    {
        $ids = [];
        foreach ($this->transport()->getSent() as $envelope) {
            if ($envelope->getMessage() instanceof CheckOutingChanges) {
                $ids = [...$ids, ...$envelope->getMessage()->eventIds];
            }
        }

        return $ids;
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
