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
use DateTime;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testMajEndDateSetsEndDateFromStartDateWhenNoTimesheets(): void
    {
        $event = new Event();
        $event->setStartDate(new DateTime('2024-01-15'));
        $event->setEndDate(null);

        $event->updateEndDate();

        self::assertNotNull($event->getEndDate());
        self::assertEquals('2024-01-15', $event->getEndDate()->format('Y-m-d'));
    }

    public function testMajEndDateDoesNotOverrideExistingEndDateWhenNoTimesheets(): void
    {
        $event = new Event();
        $event->setStartDate(new DateTime('2024-01-15'));
        $event->setEndDate(new DateTime('2024-01-20'));

        $event->updateEndDate();

        self::assertEquals('2024-01-20', $event->getEndDate()->format('Y-m-d'));
    }

    public function testMajEndDateHandlesTimesheetsWithNullDates(): void
    {
        $event = new Event();
        $event->setStartDate(new DateTime('2024-01-01'));
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
