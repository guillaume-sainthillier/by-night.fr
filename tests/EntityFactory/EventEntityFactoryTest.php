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
use App\Dto\EventTimesheetDto;
use App\Entity\Event;
use App\Entity\EventTimesheet;
use App\EntityFactory\EventEntityFactory;
use App\Enum\EventStatus;
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

    public function testAnEventListedAgainByItsSourceIsBackInTheListings(): void
    {
        $removed = new Event()->markRemovedAtSource();
        $dto = new EventDto();
        $dto->name = 'Nuit du jazz';
        $dto->status = EventStatus::Postponed;
        $dto->startDate = new DateTimeImmutable('2026-10-01');
        $dto->endDate = new DateTimeImmutable('2026-10-01');

        $event = self::getContainer()->get(EventEntityFactory::class)->create($removed, $dto);

        self::assertInstanceOf(Event::class, $event);
        self::assertSame(EventStatus::Postponed, $event->getStatus(), 'Its own status, as the source gives it');
        self::assertFalse($event->isDraft());
    }

    public function testAnImportLeavesAHiddenEventHidden(): void
    {
        $hidden = new Event()->setDraft(true);
        $dto = new EventDto();
        $dto->name = 'Nuit du jazz';
        $dto->startDate = new DateTimeImmutable('2026-10-01');
        $dto->endDate = new DateTimeImmutable('2026-10-01');

        $event = self::getContainer()->get(EventEntityFactory::class)->create($hidden, $dto);

        self::assertInstanceOf(Event::class, $event);
        self::assertTrue($event->isDraft(), 'Hidden by an administrator, not by its source');
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

    public function testTheTimesOfTheSessionsAndOfTheEventAreStored(): void
    {
        $dto = $this->twoSessionsOnOneDay();

        $event = self::getContainer()->get(EventEntityFactory::class)->create(null, $dto);

        self::assertInstanceOf(Event::class, $event);
        self::assertSame('15:00', $event->getStartTime()?->format('H:i'));
        self::assertSame('23:00', $event->getEndTime()?->format('H:i'));
        self::assertSame(
            [['15:00', '17:00'], ['20:30', '23:00']],
            $this->times($event),
            'Two sessions of the same day are told apart by their times',
        );
    }

    public function testAnUnchangedSessionKeepsItsRowWhileAMovedOneIsReplaced(): void
    {
        $factory = self::getContainer()->get(EventEntityFactory::class);
        $event = $factory->create(null, $this->twoSessionsOnOneDay());
        self::assertInstanceOf(Event::class, $event);
        [$afternoon, $evening] = $event->getTimesheets()->toArray();

        $dto = $this->twoSessionsOnOneDay();
        $dto->timesheets[1]->startTime = new DateTimeImmutable('21:00');
        $factory->create($event, $dto);

        self::assertSame([['15:00', '17:00'], ['21:00', '23:00']], $this->times($event));
        self::assertContains($afternoon, $event->getTimesheets());
        self::assertNotContains($evening, $event->getTimesheets());
    }

    private function twoSessionsOnOneDay(): EventDto
    {
        $dto = new EventDto();
        $dto->name = 'Nuit du jazz';
        $dto->startDate = new DateTimeImmutable('2026-10-01');
        $dto->endDate = new DateTimeImmutable('2026-10-01');
        $dto->startTime = new DateTimeImmutable('15:00');
        $dto->endTime = new DateTimeImmutable('23:00');
        foreach ([['15:00', '17:00'], ['20:30', '23:00']] as [$start, $end]) {
            $timesheet = new EventTimesheetDto();
            $timesheet->startAt = new DateTimeImmutable('2026-10-01');
            $timesheet->endAt = new DateTimeImmutable('2026-10-01');
            $timesheet->startTime = new DateTimeImmutable($start);
            $timesheet->endTime = new DateTimeImmutable($end);
            $dto->timesheets[] = $timesheet;
        }

        return $dto;
    }

    /**
     * @return list<array{0: string|null, 1: string|null}>
     */
    private function times(Event $event): array
    {
        $times = array_map(static fn (EventTimesheet $timesheet): array => [
            $timesheet->getStartTime()?->format('H:i'),
            $timesheet->getEndTime()?->format('H:i'),
        ], $event->getTimesheets()->toArray());
        sort($times);

        return $times;
    }
}
