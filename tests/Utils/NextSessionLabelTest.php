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
use App\Utils\NextSessionLabel;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class NextSessionLabelTest extends TestCase
{
    private const string TODAY = '2026-10-24';

    public function testAnEventToComeSaysItsNextSessionAndWhenItStarts(): void
    {
        $event = new Event()->setStartDate(new DateTimeImmutable('2026-04-17'));
        $event->addTimesheet(self::session('2026-04-17', '2026-04-17'));
        $event->addTimesheet(self::session('2026-10-31', '2026-10-31', '20:30'));

        self::assertSame('sam. 31 oct. · 20h30', NextSessionLabel::of($event, new DateTimeImmutable(self::TODAY)));
    }

    public function testADayWithoutTimeSaysTheDayAlone(): void
    {
        $event = new Event()->setStartDate(new DateTimeImmutable('2026-10-25'));

        self::assertSame('dim. 25 oct.', NextSessionLabel::of($event, new DateTimeImmutable(self::TODAY)));
    }

    public function testARunStillGoingSaysWhenItEnds(): void
    {
        $event = new Event()
            ->setStartDate(new DateTimeImmutable('2026-04-17'))
            ->setEndDate(new DateTimeImmutable('2026-11-05'));

        self::assertSame('Jusqu\'au 5 nov.', NextSessionLabel::of($event, new DateTimeImmutable(self::TODAY)));
    }

    private static function session(string $start, string $end, ?string $time = null): EventTimesheet
    {
        return new EventTimesheet()
            ->setStartAt(new DateTimeImmutable($start))
            ->setEndAt(new DateTimeImmutable($end))
            ->setStartTime(null !== $time ? new DateTimeImmutable($time) : null);
    }
}
