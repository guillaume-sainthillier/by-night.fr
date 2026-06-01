<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Command\EventsMergeDuplicatesCommand;
use App\Entity\Event;
use App\Entity\EventTimesheet;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Unit tests for the pure grouping / merging logic of the duplicate merge command.
 * The DB-backed plumbing (query, pagination, re-fetch) is exercised separately; here
 * we drive the in-memory decision logic with reflection so no database is required.
 */
final class EventsMergeDuplicatesCommandTest extends TestCase
{
    private EventsMergeDuplicatesCommand $command;

    protected function setUp(): void
    {
        $this->command = (new ReflectionClass(EventsMergeDuplicatesCommand::class))->newInstanceWithoutConstructor();
    }

    public function testSuffixStrategyGroupsSiblingsBySharedBase(): void
    {
        // Suffixed siblings share a base once the trailing "-N" is stripped.
        $first = $this->event(1, 'SP-99999-0', 'kwanko.seetickets');
        $second = $this->event(2, 'SP-99999-1', 'kwanko.seetickets');
        $third = $this->event(3, 'SP-99999-12', 'kwanko.seetickets');
        $otherShow = $this->event(4, 'SP-88888-0', 'kwanko.seetickets');

        $key = $this->groupKey($first, 'suffix');
        self::assertSame('kwanko.seetickets|SP-99999', $key);
        self::assertSame($key, $this->groupKey($second, 'suffix'));
        self::assertSame($key, $this->groupKey($third, 'suffix'));
        self::assertNotSame($key, $this->groupKey($otherShow, 'suffix'));
    }

    public function testContentStrategyGroupsByOriginPlaceAndName(): void
    {
        $a = $this->event(1, '21628777', 'awin.fnac', 'Van Gogh', 'place-hash');
        $b = $this->event(2, '21628778', 'awin.fnac', 'Van Gogh', 'place-hash');
        $differentPlace = $this->event(3, '21628779', 'awin.fnac', 'Van Gogh', 'other-place');

        $key = $this->groupKey($a, 'content');
        self::assertSame('awin.fnac|place-hash|Van Gogh', $key);
        self::assertSame($key, $this->groupKey($b, 'content'));
        self::assertNotSame($key, $this->groupKey($differentPlace, 'content'));
    }

    public function testContentHashIdDetection(): void
    {
        $method = new ReflectionMethod($this->command, 'isContentHashId');

        self::assertTrue($method->invoke($this->command, sha1('a-show')));
        self::assertFalse($method->invoke($this->command, '21628777'));
        self::assertFalse($method->invoke($this->command, 'SP-123-0'));
        self::assertFalse($method->invoke($this->command, null));
        self::assertFalse($method->invoke($this->command, strtoupper(sha1('x'))), 'Uppercase hex is not a parser hash.');
    }

    public function testCanonicalSelectionPrefersTheContentHashEvent(): void
    {
        $legacyOld = $this->event(5, '21628777', 'awin.fnac', 'Van Gogh', 'place-hash');
        $hash = $this->event(20, sha1('Van Gogh|place-hash'), 'awin.fnac', 'Van Gogh', 'place-hash');
        $legacyNew = $this->event(30, '21628999', 'awin.fnac', 'Van Gogh', 'place-hash');

        $canonical = $this->selectCanonical([$legacyOld, $hash, $legacyNew]);

        self::assertSame($hash, $canonical, 'The stable hash event wins regardless of id ordering.');
    }

    public function testCanonicalSelectionFallsBackToOldestWhenNoHashEvent(): void
    {
        $events = [
            $this->event(10, '21628777', 'awin.fnac', 'Van Gogh', 'place-hash'),
            $this->event(4, '21628778', 'awin.fnac', 'Van Gogh', 'place-hash'),
            $this->event(8, '21628779', 'awin.fnac', 'Van Gogh', 'place-hash'),
        ];

        $canonical = $this->selectCanonical($events);

        self::assertSame(4, $canonical?->getId(), 'Oldest (lowest id) keeps its public URL.');
    }

    public function testMergeTimesheetsAddsNewDatesAndDeduplicates(): void
    {
        $canonical = $this->event(1, sha1('s'), 'awin.fnac', 'Show', 'p');
        $canonical->addTimesheet($this->timesheet('2026-08-01', 'À 20h00'));

        $duplicate = $this->event(2, '999', 'awin.fnac', 'Show', 'p');
        $duplicate->addTimesheet($this->timesheet('2026-08-01', 'À 20h00')); // same date -> deduped
        $duplicate->addTimesheet($this->timesheet('2026-08-02', 'À 20h00')); // new date -> added

        $this->mergeTimesheets($canonical, $duplicate);

        self::assertSame(['2026-08-01', '2026-08-02'], $this->timesheetDates($canonical));
        self::assertTrue($canonical->batchUpdate);
        self::assertTrue($duplicate->batchUpdate);
    }

    public function testMergeTimesheetsSynthesizesTimesheetFromLegacyDuplicateDate(): void
    {
        // Canonical already migrated to the timesheet model.
        $canonical = $this->event(1, sha1('s'), 'awin.fnac', 'Show', 'p');
        $canonical->addTimesheet($this->timesheet('2026-08-01', 'À 20h00'));

        // Legacy duplicate: a start date but no timesheet rows.
        $duplicate = $this->event(2, '999', 'awin.fnac', 'Show', 'p');
        $duplicate->setStartDate(new DateTimeImmutable('2026-08-05'));
        $duplicate->setEndDate(new DateTimeImmutable('2026-08-05'));
        $duplicate->setHours('À 18h00');

        $this->mergeTimesheets($canonical, $duplicate);

        self::assertSame(['2026-08-01', '2026-08-05'], $this->timesheetDates($canonical));
    }

    public function testMergeTimesheetsSeedsCanonicalOwnDateWhenItHasNone(): void
    {
        // Both events predate the timesheet model (legacy-only group).
        $canonical = $this->event(1, '111', 'awin.fnac', 'Show', 'p');
        $canonical->setStartDate(new DateTimeImmutable('2026-08-01'));
        $canonical->setEndDate(new DateTimeImmutable('2026-08-01'));
        $canonical->setHours('À 13h00');

        $duplicate = $this->event(2, '222', 'awin.fnac', 'Show', 'p');
        $duplicate->setStartDate(new DateTimeImmutable('2026-08-02'));
        $duplicate->setEndDate(new DateTimeImmutable('2026-08-02'));

        $this->mergeTimesheets($canonical, $duplicate);

        self::assertSame(['2026-08-01', '2026-08-02'], $this->timesheetDates($canonical));
    }

    public function testRealignDateRangeSpansEveryTimesheet(): void
    {
        $canonical = $this->event(1, sha1('s'), 'awin.fnac', 'Show', 'p');
        $canonical->addTimesheet($this->timesheet('2026-08-03', null));
        $canonical->addTimesheet($this->timesheet('2026-08-01', null));
        $canonical->addTimesheet($this->timesheet('2026-08-05', null));

        (new ReflectionMethod($this->command, 'realignDateRange'))->invoke($this->command, $canonical);

        self::assertSame('2026-08-01', $canonical->getStartDate()?->format('Y-m-d'));
        self::assertSame('2026-08-05', $canonical->getEndDate()?->format('Y-m-d'));
    }

    private function groupKey(Event $event, string $strategy): string
    {
        return (new ReflectionMethod($this->command, 'getGroupKey'))->invoke($this->command, $event, $strategy);
    }

    /**
     * @param list<Event> $events
     */
    private function selectCanonical(array $events): ?Event
    {
        return (new ReflectionMethod($this->command, 'selectCanonicalFromGroup'))->invoke($this->command, $events);
    }

    private function mergeTimesheets(Event $canonical, Event $duplicate): void
    {
        (new ReflectionMethod($this->command, 'mergeTimesheets'))->invoke($this->command, $canonical, $duplicate);
    }

    /**
     * @return list<string|null>
     */
    private function timesheetDates(Event $event): array
    {
        $dates = array_map(
            static fn (EventTimesheet $timesheet): ?string => $timesheet->getStartAt()?->format('Y-m-d'),
            $event->getTimesheets()->toArray(),
        );
        sort($dates);

        return $dates;
    }

    private function event(int $id, string $externalId, string $origin, string $name = 'Show', string $placeExternalId = 'place'): Event
    {
        $event = new Event();
        $event->setId($id);
        $event->setExternalId($externalId);
        $event->setExternalOrigin($origin);
        $event->setName($name);
        $event->setPlaceExternalId($placeExternalId);

        return $event;
    }

    private function timesheet(string $date, ?string $hours): EventTimesheet
    {
        $timesheet = new EventTimesheet();
        $timesheet->setStartAt(new DateTimeImmutable($date));
        $timesheet->setEndAt(new DateTimeImmutable($date));
        $timesheet->setHours($hours);

        return $timesheet;
    }
}
