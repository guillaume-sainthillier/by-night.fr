<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Entity;

use App\Entity\Event;
use App\Entity\EventTimesheet;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testSessionsAreTheTimesheetsInChronologicalOrder(): void
    {
        $event = new Event();
        $event->setStartDate(new DateTimeImmutable('2026-10-03'));
        $event->setEndDate(new DateTimeImmutable('2026-10-10'));
        $event->addTimesheet($this->sessionOn('2026-10-10', 'À 21h00'));
        $event->addTimesheet($this->sessionOn('2026-10-03', 'À 20h30'));

        self::assertSame(
            ['2026-10-03', '2026-10-10'],
            array_map(static fn (EventTimesheet $session): ?string => $session->getStartAt()?->format('Y-m-d'), $event->getSessions()),
        );
    }

    public function testAnEventWithoutTimesheetsHasOneSessionSpanningItsRange(): void
    {
        $event = new Event();
        $event->setStartDate(new DateTimeImmutable('2026-10-03'));
        $event->setEndDate(new DateTimeImmutable('2026-10-05'));
        $event->setHours('De 10h à 18h');

        $sessions = $event->getSessions();

        self::assertCount(1, $sessions);
        self::assertSame('2026-10-03', $sessions[0]->getStartAt()?->format('Y-m-d'));
        self::assertSame('2026-10-05', $sessions[0]->getEndAt()?->format('Y-m-d'));
        self::assertSame('De 10h à 18h', $sessions[0]->getHours());
        self::assertCount(0, $event->getTimesheets(), 'The synthesized session is not a timesheet row.');
    }

    public function testSessionForPrefersTheFirstSessionOverlappingTheWindow(): void
    {
        $event = new Event();
        $event->addTimesheet($this->sessionOn('2026-09-25'));
        $event->addTimesheet($this->sessionOn('2026-09-27'));
        $event->addTimesheet($this->sessionOn('2026-10-10'));

        $day = static fn (?EventTimesheet $session): ?string => $session?->getStartAt()?->format('Y-m-d');

        self::assertSame('2026-09-27', $day($event->getSessionFor(new DateTimeImmutable('2026-09-26'), new DateTimeImmutable('2026-09-28'))), 'The session the visitor searched for.');
        self::assertSame('2026-09-27', $day($event->getSessionFor(new DateTimeImmutable('2026-09-26'))), 'The next session as of that day.');
        self::assertSame('2026-09-25', $day($event->getSessionFor(new DateTimeImmutable('2026-09-25'))), 'A session ending today is still on.');
        self::assertSame('2026-09-27', $day($event->getSessionFor(new DateTimeImmutable('2026-09-26'), new DateTimeImmutable('2026-09-26'))), 'Nothing in the window: the next session.');
        self::assertSame('2026-10-10', $day($event->getSessionFor(new DateTimeImmutable('2026-10-11'))), 'All over: the last session.');
        self::assertNull(new Event()->setStartDate(null)->getSessionFor(), 'No date at all, nothing to show.');
    }

    public function testCountUpcomingSessions(): void
    {
        $event = new Event();
        $event->addTimesheet($this->sessionOn('2026-09-25'));
        $event->addTimesheet($this->sessionOn('2026-09-27'));
        $event->addTimesheet($this->sessionOn('2026-10-10'));

        self::assertSame(3, $event->countUpcomingSessions(new DateTimeImmutable('2026-09-25')));
        self::assertSame(2, $event->countUpcomingSessions(new DateTimeImmutable('2026-09-26')));
        self::assertSame(0, $event->countUpcomingSessions(new DateTimeImmutable('2026-10-11')));
    }

    private function sessionOn(string $date, ?string $hours = null): EventTimesheet
    {
        return new EventTimesheet()
            ->setStartAt(new DateTimeImmutable($date))
            ->setEndAt(new DateTimeImmutable($date))
            ->setHours($hours);
    }

    public function testMajEndDateSetsEndDateFromStartDateWhenNoTimesheets(): void
    {
        $event = new Event();
        $event->setStartDate(new DateTimeImmutable('2024-01-15'));
        $event->setEndDate(null);

        $event->updateEndDate();

        self::assertNotNull($event->getEndDate());
        self::assertEquals('2024-01-15', $event->getEndDate()->format('Y-m-d'));
    }

    public function testMajEndDateDoesNotOverrideExistingEndDateWhenNoTimesheets(): void
    {
        $event = new Event();
        $event->setStartDate(new DateTimeImmutable('2024-01-15'));
        $event->setEndDate(new DateTimeImmutable('2024-01-20'));

        $event->updateEndDate();

        self::assertEquals('2024-01-20', $event->getEndDate()->format('Y-m-d'));
    }

    public function testMajEndDateHandlesTimesheetsWithNullDates(): void
    {
        $event = new Event();
        $event->setStartDate(new DateTimeImmutable('2024-01-01'));
        $event->setEndDate(null);

        // Add a timesheet with null dates (edge case)
        $timesheet = new EventTimesheet();
        $timesheet->setStartAt(null);
        $timesheet->setEndAt(null);

        $event->addTimesheet($timesheet);

        $event->updateEndDate();

        // When timesheets have null dates, should fallback to setting endDate = startDate
        self::assertEquals($event->getStartDate(), $event->getEndDate());
    }

    public function testAddTimesheetSetsEventRelation(): void
    {
        $event = new Event();
        $timesheet = new EventTimesheet();

        $event->addTimesheet($timesheet);

        self::assertSame($event, $timesheet->getEvent());
        self::assertCount(1, $event->getTimesheets());
    }

    public function testAddTimesheetDoesNotAddDuplicate(): void
    {
        $event = new Event();
        $timesheet = new EventTimesheet();

        $event->addTimesheet($timesheet);
        $event->addTimesheet($timesheet);

        self::assertCount(1, $event->getTimesheets());
    }

    public function testRemoveTimesheetClearsEventRelation(): void
    {
        $event = new Event();
        $timesheet = new EventTimesheet();
        $event->addTimesheet($timesheet);

        $event->removeTimesheet($timesheet);

        self::assertNull($timesheet->getEvent());
        self::assertCount(0, $event->getTimesheets());
    }

    public function testGetDuplicateOfReturnsNullByDefault(): void
    {
        $event = new Event();

        self::assertNull($event->getDuplicateOf());
    }

    public function testSetDuplicateOfCreatesRelation(): void
    {
        $canonical = new Event();
        $canonical->setId(1);

        $duplicate = new Event();
        $duplicate->setId(2);
        $duplicate->setDuplicateOf($canonical);

        self::assertSame($canonical, $duplicate->getDuplicateOf());
    }

    public function testIsDuplicateReturnsFalseByDefault(): void
    {
        $event = new Event();

        self::assertFalse($event->isDuplicate());
    }

    public function testIsDuplicateReturnsTrueWhenSet(): void
    {
        $canonical = new Event();
        $duplicate = new Event();
        $duplicate->setDuplicateOf($canonical);

        self::assertTrue($duplicate->isDuplicate());
    }

    public function testGetCanonicalEventReturnsSelfWhenNotDuplicate(): void
    {
        $event = new Event();

        self::assertSame($event, $event->getCanonicalEvent());
    }

    public function testGetCanonicalEventReturnsCanonicalWhenDuplicate(): void
    {
        $canonical = new Event();
        $duplicate = new Event();
        $duplicate->setDuplicateOf($canonical);

        self::assertSame($canonical, $duplicate->getCanonicalEvent());
    }

    public function testSetDuplicateOfCanBeCleared(): void
    {
        $canonical = new Event();
        $duplicate = new Event();
        $duplicate->setDuplicateOf($canonical);
        $duplicate->setDuplicateOf(null);

        self::assertNull($duplicate->getDuplicateOf());
        self::assertFalse($duplicate->isDuplicate());
    }
}
