<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EntityFactory;

use App\Dto\EventDto;
use App\Entity\Event;
use App\EntityFactory\EventEntityFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

final class EventEntityFactoryTest extends AppKernelTestCase
{
    public function testTheTypeGivenByTheSourceIsStored(): void
    {
        $dto = new EventDto();
        $dto->name = 'Nuit du jazz';
        $dto->type = 'Concert';
        $dto->startDate = new DateTimeImmutable('2026-10-01');
        $dto->endDate = new DateTimeImmutable('2026-10-01');

        $event = self::getContainer()->get(EventEntityFactory::class)->create(null, $dto);

        self::assertInstanceOf(Event::class, $event);
        self::assertSame('Concert', $event->getType());
    }

    public function testTheParserVersionIsStoredForTheExplorationOfADeletedEvent(): void
    {
        $dto = new EventDto();
        $dto->name = 'Nuit du jazz';
        $dto->parserVersion = '4.0';
        $dto->startDate = new DateTimeImmutable('2026-10-01');
        $dto->endDate = new DateTimeImmutable('2026-10-01');

        $event = self::getContainer()->get(EventEntityFactory::class)->create(null, $dto);

        self::assertInstanceOf(Event::class, $event);
        self::assertSame('4.0', $event->getParserVersion());
    }
}
