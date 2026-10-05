<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Entity\EventTimesheet;
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The hours labels stored before the times move into them, as an import now cleans them.
 */
final class EventsBackfillTimesCommandTest extends AppKernelTestCase
{
    public function testTheLabelsThatOnlyStateASlotBecomeTimes(): void
    {
        $event = EventFactory::createOne([
            'externalId' => 'oa-1',
            'externalOrigin' => 'openagenda',
            'startDate' => new DateTimeImmutable('2026-10-01'),
            'endDate' => new DateTimeImmutable('2026-10-03'),
            'hours' => 'De 20h00 à 23h00',
        ]);
        $slot = $this->session($event->getId(), '2026-10-01', 'De 20h00 à 23h00');
        $prose = $this->session($event->getId(), '2026-10-02', 'Jeudi à 20h, puis à 22h');
        $wholeDay = $this->session($event->getId(), '2026-10-03', 'De 02h00 à 01h59');

        $this->doRunCommand(['--apply' => true]);

        self::assertSame(['20:00:00', '23:00:00', null], $this->timesheet($slot));
        self::assertSame([null, null, 'Jeudi à 20h, puis à 22h'], $this->timesheet($prose));
        self::assertSame([null, null, null], $this->timesheet($wholeDay), 'The whole day is no time, not the label the parser summed the event up with');

        $event = EventFactory::find(['id' => $event->getId()]);
        self::assertSame('20:00', $event->getStartTime()?->format('H:i'), 'The first session starts the event');
        self::assertNull($event->getEndTime(), 'The last session gives no end');
        self::assertNull($event->getHours());
    }

    public function testAnEventWithoutTimesheetsGetsTheTimesOfItsLabel(): void
    {
        $event = EventFactory::createOne(['hours' => 'A 20h30.']);
        $prose = EventFactory::createOne(['hours' => 'Du mardi au dimanche de 10h à 18h.']);

        $this->doRunCommand(['--apply' => true]);

        $event = EventFactory::find(['id' => $event->getId()]);
        self::assertSame(['20:30', null, null], [$event->getStartTime()?->format('H:i'), $event->getEndTime()?->format('H:i'), $event->getHours()]);
        self::assertSame('Du mardi au dimanche de 10h à 18h.', EventFactory::find(['id' => $prose->getId()])->getHours());
    }

    public function testTheDatesWithoutHoursTakeTheDefaultSlotOfTheirEvent(): void
    {
        // As the event form saved them: the default hours on the event, the dates without their own
        $event = EventFactory::createOne(['hours' => '21h-05h']);
        $default = $this->session($event->getId(), '2026-10-01', null);
        $own = $this->session($event->getId(), '2026-10-02', 'À 19h');

        $this->doRunCommand(['--apply' => true]);

        self::assertSame(['21:00:00', '05:00:00', null], $this->timesheet($default));
        self::assertSame(['19:00:00', null, null], $this->timesheet($own));
        $event = EventFactory::find(['id' => $event->getId()]);
        self::assertSame(['21:00', null, null], [$event->getStartTime()?->format('H:i'), $event->getEndTime()?->format('H:i'), $event->getHours()]);
    }

    public function testPreviewWritesNothingAndASecondRunFindsNothingLeft(): void
    {
        $event = EventFactory::createOne(['hours' => 'À 20h30']);
        $session = $this->session($event->getId(), '2026-10-01', 'À 20h30');

        self::assertStringContainsString('1 timesheet(s), 1 event(s) and 0 date(s)', $this->doRunCommand([])->getDisplay());
        self::assertSame([null, null, 'À 20h30'], $this->timesheet($session));

        $this->doRunCommand(['--apply' => true]);
        self::assertStringContainsString('0 timesheet(s), 0 event(s) and 0 date(s)', $this->doRunCommand([])->getDisplay());
    }

    /**
     * A date without times, as stored before them.
     */
    private function session(?int $eventId, string $day, ?string $hours): int
    {
        $timesheet = EventTimesheetFactory::new()->on($day, $hours)->create([
            'event' => EventFactory::find(['id' => $eventId]),
            'startTime' => null,
        ]);

        return (int) $timesheet->getId();
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function timesheet(int $id): array
    {
        $timesheet = EventTimesheetFactory::find(['id' => $id]);
        self::assertInstanceOf(EventTimesheet::class, $timesheet);

        return [$timesheet->getStartTime()?->format('H:i:s'), $timesheet->getEndTime()?->format('H:i:s'), $timesheet->getHours()];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:events:backfill-times'));
        $tester->execute($input);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester;
    }
}
