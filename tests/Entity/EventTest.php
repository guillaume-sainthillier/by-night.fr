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

    public function testMajEndDateComputesMinMaxFromTimesheets(): void
    {
        $event = new Event();
        $event->setStartDate(new DateTime('2024-01-01')); // Will be overwritten

        $timesheet1 = new EventTimesheet();
        $timesheet1->setStartAt(new DateTime('2024-01-15 10:00:00'));
        $timesheet1->setEndAt(new DateTime('2024-01-15 18:00:00'));
        $event->addTimesheet($timesheet1);

        $timesheet2 = new EventTimesheet();
        $timesheet2->setStartAt(new DateTime('2024-01-20 14:00:00'));
        $timesheet2->setEndAt(new DateTime('2024-01-22 22:00:00'));
        $event->addTimesheet($timesheet2);

        $timesheet3 = new EventTimesheet();
        $timesheet3->setStartAt(new DateTime('2024-01-10 09:00:00'));
        $timesheet3->setEndAt(new DateTime('2024-01-10 17:00:00'));
        $event->addTimesheet($timesheet3);

        $event->updateEndDate();

        // startDate should be min of all startAt (2024-01-10), time set to 00:00:00
        self::assertEquals('2024-01-10', $event->getStartDate()->format('Y-m-d'));
        self::assertEquals('00:00:00', $event->getStartDate()->format('H:i:s'));

        // endDate should be max of all endAt (2024-01-22), time set to 00:00:00
        self::assertEquals('2024-01-22', $event->getEndDate()->format('Y-m-d'));
        self::assertEquals('00:00:00', $event->getEndDate()->format('H:i:s'));
    }

    public function testMajEndDateHandlesSingleTimesheet(): void
    {
        $event = new Event();

        $timesheet = new EventTimesheet();
        $timesheet->setStartAt(new DateTime('2024-01-15 10:00:00'));
        $timesheet->setEndAt(new DateTime('2024-01-15 18:00:00'));
        $event->addTimesheet($timesheet);

        $event->updateEndDate();

        self::assertEquals('2024-01-15', $event->getStartDate()->format('Y-m-d'));
        self::assertEquals('2024-01-15', $event->getEndDate()->format('Y-m-d'));
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
