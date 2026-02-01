<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Command\EventsStatusMigrateCommand;
use App\Enum\EventStatus;
use PHPUnit\Framework\TestCase;

final class EventsStatusMigrateCommandTest extends TestCase
{
    private EventsStatusMigrateCommand $command;

    protected function setUp(): void
    {
        // Create a partial mock to test the mapping function
        $this->command = $this->getMockBuilder(EventsStatusMigrateCommand::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    public function testMapStatusMessageToEnumReturnsNullForNullInput(): void
    {
        $result = $this->command->mapStatusMessageToEnum(null);
        self::assertNull($result);
    }

    public function testMapStatusMessageToEnumReturnsNullForEmptyString(): void
    {
        $result = $this->command->mapStatusMessageToEnum('');
        self::assertNull($result);
    }

    public function testMapStatusMessageToEnumReturnsCancelledForAnnule(): void
    {
        $result = $this->command->mapStatusMessageToEnum('ANNULE');
        self::assertSame(EventStatus::Cancelled, $result);
    }

    public function testMapStatusMessageToEnumReturnsCancelledForAnnuleCaseInsensitive(): void
    {
        $result = $this->command->mapStatusMessageToEnum('annulé');
        self::assertSame(EventStatus::Cancelled, $result);
    }

    public function testMapStatusMessageToEnumReturnsCancelledForAnnulationPartial(): void
    {
        $result = $this->command->mapStatusMessageToEnum('Événement annulé');
        self::assertSame(EventStatus::Cancelled, $result);
    }

    public function testMapStatusMessageToEnumReturnsPostponedForReporte(): void
    {
        $result = $this->command->mapStatusMessageToEnum('REPORTE');
        self::assertSame(EventStatus::Postponed, $result);
    }

    public function testMapStatusMessageToEnumReturnsPostponedForReporteCaseInsensitive(): void
    {
        $result = $this->command->mapStatusMessageToEnum('reporté');
        self::assertSame(EventStatus::Postponed, $result);
    }

    public function testMapStatusMessageToEnumReturnsPostponedForReportPartial(): void
    {
        $result = $this->command->mapStatusMessageToEnum('Événement reporté à une date ultérieure');
        self::assertSame(EventStatus::Postponed, $result);
    }

    public function testMapStatusMessageToEnumReturnsSoldOutForComplet(): void
    {
        $result = $this->command->mapStatusMessageToEnum('COMPLET');
        self::assertSame(EventStatus::SoldOut, $result);
    }

    public function testMapStatusMessageToEnumReturnsSoldOutForCompletCaseInsensitive(): void
    {
        $result = $this->command->mapStatusMessageToEnum('complet');
        self::assertSame(EventStatus::SoldOut, $result);
    }

    public function testMapStatusMessageToEnumReturnsSoldOutForCompletPartial(): void
    {
        $result = $this->command->mapStatusMessageToEnum('Salle complète');
        self::assertSame(EventStatus::SoldOut, $result);
    }

    public function testMapStatusMessageToEnumReturnsScheduledForUnknownValue(): void
    {
        $result = $this->command->mapStatusMessageToEnum('UNKNOWN_STATUS');
        self::assertSame(EventStatus::Scheduled, $result);
    }

    public function testMapStatusMessageToEnumReturnsScheduledForRandomText(): void
    {
        $result = $this->command->mapStatusMessageToEnum('Some random status text');
        self::assertSame(EventStatus::Scheduled, $result);
    }
}
