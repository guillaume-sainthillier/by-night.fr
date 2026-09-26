<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Search;

use App\Entity\Event;
use App\Entity\EventTimesheet;
use App\Search\AgendaSection;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The agenda lists each event on the day it sorts it by, so that the days follow each other in order, each one once.
 */
final class AgendaSectionTest extends TestCase
{
    public function testTheEventsOfADayShareItsHeading(): void
    {
        $concert = $this->eventOn('2026-09-26');
        $play = $this->eventOn('2026-09-26');
        $market = $this->eventOn('2026-09-27');

        $sections = AgendaSection::fromEvents([$concert, $play, $market], new DateTimeImmutable('2026-09-26'));

        self::assertSame(['2026-09-26', '2026-09-27'], $this->daysOf($sections));
        self::assertSame([$concert, $play], $sections[0]->events);
        self::assertSame([$market], $sections[1]->events);
    }

    /**
     * Listed on its first day, an exhibition running since May would come under a heading of May, among the events of
     * the end of September.
     */
    public function testAnExhibitionIsListedOnItsLastDay(): void
    {
        $exhibition = $this->eventFromTo('2026-05-09', '2026-09-30');

        $sections = AgendaSection::fromEvents([$this->eventOn('2026-09-29'), $exhibition, $this->eventOn('2026-09-30')], new DateTimeImmutable('2026-09-26'));

        self::assertSame(['2026-09-29', '2026-09-30'], $this->daysOf($sections));
        self::assertSame($exhibition, $sections[1]->events[0]);
    }

    public function testAnEventIsListedOnItsSoonestEndingSessionOfTheWindow(): void
    {
        $exhibition = new Event();
        $exhibition->addTimesheet($this->session('2026-09-01', '2026-10-30'));
        $exhibition->addTimesheet($this->session('2026-09-28', '2026-09-28'));
        $exhibition->addTimesheet($this->session('2026-09-20', '2026-09-20'));

        $sections = AgendaSection::fromEvents([$exhibition], new DateTimeImmutable('2026-09-26'));

        self::assertSame(['2026-09-28'], $this->daysOf($sections), 'Its opening, not the exhibition, nor the day gone by');
    }

    public function testAnEventEndingAfterTheWindowIsListedOnItsLastDay(): void
    {
        $festival = $this->eventFromTo('2026-09-26', '2026-09-29');

        $sections = AgendaSection::fromEvents([$this->eventOn('2026-09-27'), $festival], new DateTimeImmutable('2026-09-25'), new DateTimeImmutable('2026-09-27'));

        self::assertSame(['2026-09-27'], $this->daysOf($sections), 'Searching the weekend lists no Tuesday');
        self::assertCount(2, $sections[0]->events);
    }

    /**
     * @param list<AgendaSection> $sections
     *
     * @return list<string>
     */
    private function daysOf(array $sections): array
    {
        return array_map(static fn (AgendaSection $section): string => $section->day->format('Y-m-d'), $sections);
    }

    private function eventOn(string $day): Event
    {
        return $this->eventFromTo($day, $day);
    }

    private function eventFromTo(string $start, string $end): Event
    {
        return new Event()
            ->setStartDate(new DateTimeImmutable($start))
            ->setEndDate(new DateTimeImmutable($end));
    }

    private function session(string $start, string $end): EventTimesheet
    {
        return new EventTimesheet()
            ->setStartAt(new DateTimeImmutable($start))
            ->setEndAt(new DateTimeImmutable($end));
    }
}
