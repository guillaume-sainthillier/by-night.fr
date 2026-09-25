<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Entity\Event;
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * An event stored with its end before its start takes the range of its sessions.
 */
final class EventsFixInvertedDatesCommandTest extends AppKernelTestCase
{
    public function testAnInvertedEventTakesTheRangeOfItsSessions(): void
    {
        $inverted = $this->eventWithSessions('2026-05-30', '2026-04-25', ['2026-04-25', '2026-06-13', '2026-10-10']);
        $right = $this->eventWithSessions('2026-06-01', '2026-06-02', ['2026-06-01', '2026-06-02']);

        $this->doRunCommand(['--apply' => true]);

        $inverted = EventFactory::find(['id' => $inverted->getId()]);
        self::assertSame(['2026-04-25', '2026-10-10'], [$inverted->getStartDate()?->format('Y-m-d'), $inverted->getEndDate()?->format('Y-m-d')]);
        $right = EventFactory::find(['id' => $right->getId()]);
        self::assertSame(['2026-06-01', '2026-06-02'], [$right->getStartDate()?->format('Y-m-d'), $right->getEndDate()?->format('Y-m-d')]);
    }

    public function testPreviewWritesNothing(): void
    {
        $inverted = $this->eventWithSessions('2026-05-30', '2026-04-25', ['2026-04-25', '2026-10-10']);

        $display = $this->doRunCommand([])->getDisplay();

        self::assertStringContainsString('1 event(s) would be fixed', $display);
        self::assertSame('2026-05-30', EventFactory::find(['id' => $inverted->getId()])->getStartDate()?->format('Y-m-d'));
    }

    /**
     * @param list<string> $sessions
     */
    private function eventWithSessions(string $start, string $end, array $sessions): Event
    {
        $event = EventFactory::createOne(['startDate' => new DateTimeImmutable($start), 'endDate' => new DateTimeImmutable($end)]);
        foreach ($sessions as $day) {
            EventTimesheetFactory::createOne(['event' => $event, 'startAt' => new DateTimeImmutable($day . ' 20:00'), 'endAt' => new DateTimeImmutable($day . ' 22:00')]);
        }

        return $event;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:events:fix-inverted-dates'));
        $tester->execute($input);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester;
    }
}
