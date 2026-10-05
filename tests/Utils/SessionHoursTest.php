<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Entity\Event;
use App\Entity\EventTimesheet;
use App\Utils\SessionHours;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SessionHoursTest extends TestCase
{
    #[DataProvider('slots')]
    public function testASlotIsWrittenFromItsTimes(?string $start, ?string $end, ?string $expected): void
    {
        self::assertSame($expected, SessionHours::format(self::time($start), self::time($end)));
    }

    /**
     * @return iterable<string, array{0: string|null, 1: string|null, 2: string|null}>
     */
    public static function slots(): iterable
    {
        yield 'no time' => [null, null, null];
        yield 'a start' => ['20:30', null, 'À 20h30'];
        yield 'a start on the hour' => ['20:00', null, 'À 20h'];
        yield 'a morning start' => ['09:05', null, 'À 9h05'];
        yield 'a slot' => ['20:00', '23:30', 'De 20h à 23h30'];
        yield 'past midnight' => ['23:00', '05:00', 'De 23h à 5h'];
        yield 'until midnight' => ['21:00', '00:00', 'De 21h à minuit'];
        yield 'from noon' => ['12:00', '14:00', 'De midi à 14h'];
        yield 'a night from midnight' => ['00:00', '06:00', 'De minuit à 6h'];
        yield 'an end alone' => [null, '23:00', "Jusqu'à 23h"];
    }

    public function testASessionShowsItsTimesThenWhatTheyCannotSay(): void
    {
        $session = self::session('20:00', '23:00', 'Ouverture des portes à 19h');

        self::assertSame('De 20h à 23h, Ouverture des portes à 19h', SessionHours::ofSession($session));
    }

    public function testASessionWithoutHoursShowsTheDefaultOfItsEvent(): void
    {
        $event = new Event()->setHours('À 20h, de 21h à minuit');
        $default = self::session(null, null, null);
        $own = self::session('19:00', null, null);
        $event->addTimesheet($default);
        $event->addTimesheet($own);

        self::assertSame('À 20h, de 21h à minuit', SessionHours::ofSession($default));
        self::assertSame('À 19h', SessionHours::ofSession($own), 'Its own times win over the default');
    }

    public function testAnEventShowsTheHoursEverySessionShares(): void
    {
        $event = new Event()->setStartDate(new DateTimeImmutable('2026-10-01'));
        $event->addTimesheet(self::session('20:00', '23:00', null, '2026-10-01'));
        $event->addTimesheet(self::session('20:00', '23:00', null, '2026-10-02'));

        self::assertSame('De 20h à 23h', SessionHours::ofEvent($event));

        $event->addTimesheet(self::session('15:00', null, null, '2026-10-03'));
        self::assertNull(SessionHours::ofEvent($event), 'Each date shows its own');
    }

    public function testAnEventWithoutTimesheetsShowsItsOwnTimes(): void
    {
        $event = new Event()
            ->setStartDate(new DateTimeImmutable('2026-10-01'))
            ->setStartTime(new DateTimeImmutable('20:30'));

        self::assertSame('À 20h30', SessionHours::ofEvent($event));
        self::assertNull(SessionHours::ofEvent(new Event()->setStartDate(new DateTimeImmutable('2026-10-01'))));
    }

    private static function session(?string $start, ?string $end, ?string $hours, string $day = '2026-10-01'): EventTimesheet
    {
        return new EventTimesheet()
            ->setStartAt(new DateTimeImmutable($day))
            ->setEndAt(new DateTimeImmutable($day))
            ->setStartTime(self::time($start))
            ->setEndTime(self::time($end))
            ->setHours($hours);
    }

    private static function time(?string $time): ?DateTimeImmutable
    {
        return null === $time ? null : (DateTimeImmutable::createFromFormat('!H:i', $time) ?: null);
    }
}
