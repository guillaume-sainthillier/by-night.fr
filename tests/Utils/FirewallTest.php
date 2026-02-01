<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Dto\EventDto;
use App\Dto\EventTimesheetDto;
use App\Entity\ParserData;
use App\Reject\Reject;
use App\Tests\AppKernelTestCase;
use App\Utils\Firewall;
use DateTimeImmutable;
use Override;

final class FirewallTest extends AppKernelTestCase
{
    protected Firewall $firewall;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->firewall = self::getContainer()->get(Firewall::class);
    }

    public function testExplorations(): void
    {
        $noNeedToUpdateReject = new Reject()->addReason(Reject::NO_NEED_TO_UPDATE);
        $badReject = new Reject()->addReason(Reject::BAD_PLACE_LOCATION);
        $deletedReject = new Reject()->addReason(Reject::EVENT_DELETED);

        $now = new DateTimeImmutable();
        $tomorrow = new DateTimeImmutable('tomorrow');

        // L'événement ne doit pas être valide car il n'a pas changé
        $exploration = new ParserData()->setReject(clone $noNeedToUpdateReject)->setLastUpdated($now);
        $event = (new EventDto());
        $event->externalUpdatedAt = $now;

        $this->firewall->filterEventExploration($exploration, $event);
        $reject = $exploration->getReject();
        self::assertNotNull($reject);
        self::assertEquals(Reject::NO_NEED_TO_UPDATE | Reject::VALID, $reject->getReason());
        self::assertEquals(Firewall::VERSION, $exploration->getFirewallVersion());

        // L'événement doit être valide car il a été mis à jour
        $exploration = new ParserData()->setReject(clone $noNeedToUpdateReject)->setLastUpdated($now);
        $event = (new EventDto());
        $event->externalUpdatedAt = $tomorrow;

        $this->firewall->filterEventExploration($exploration, $event);
        $reject = $exploration->getReject();
        self::assertNotNull($reject);
        self::assertEquals(Reject::VALID, $reject->getReason());
        self::assertEquals(Firewall::VERSION, $exploration->getFirewallVersion());

        // L'événement ne doit pas être valide car la version du firewall a changé mais qu'il n'a pas changé
        $exploration = new ParserData()->setReject(clone $noNeedToUpdateReject)->setLastUpdated($now)->setFirewallVersion('old version');
        $event = (new EventDto());
        $event->externalUpdatedAt = $now;

        $this->firewall->filterEventExploration($exploration, $event);
        $reject = $exploration->getReject();
        self::assertNotNull($reject);
        self::assertEquals(Reject::NO_NEED_TO_UPDATE | Reject::VALID, $reject->getReason());
        self::assertEquals(Firewall::VERSION, $exploration->getFirewallVersion());

        // L'événement doit être valide car la version du firewall a changé et qu'il n'était pas valide avant
        $exploration = new ParserData()->setReject($badReject)->setLastUpdated($now)->setFirewallVersion('old version');
        $event = (new EventDto());
        $event->reject = new Reject();

        $this->firewall->filterEventExploration($exploration, $event);
        $reject = $exploration->getReject();
        self::assertNotNull($reject);
        self::assertEquals(Reject::VALID, $reject->getReason());
        self::assertEquals(Firewall::VERSION, $exploration->getFirewallVersion());

        // L'événement ne doit pas être mis à jour car son créateur l'a supprimé
        $exploration = new ParserData()->setReject(clone $deletedReject)->setLastUpdated($now)->setFirewallVersion('old version');
        $event = (new EventDto());
        $event->reject = new Reject();
        $event->parserVersion = 'new version';

        $this->firewall->filterEventExploration($exploration, $event);
        $reject = $exploration->getReject();
        self::assertNotNull($reject);
        self::assertEquals(Reject::EVENT_DELETED | Reject::VALID, $reject->getReason());
        self::assertEquals('old version', $exploration->getFirewallVersion());
    }

    public function testValidTimesheetsPassValidation(): void
    {
        $dto = $this->createValidEventDto();

        $timesheet1 = new EventTimesheetDto();
        $timesheet1->startAt = new DateTimeImmutable('2024-01-15 10:00:00');
        $timesheet1->endAt = new DateTimeImmutable('2024-01-15 18:00:00');

        $timesheet2 = new EventTimesheetDto();
        $timesheet2->startAt = new DateTimeImmutable('2024-01-20 14:00:00');
        $timesheet2->endAt = new DateTimeImmutable('2024-01-20 22:00:00');

        $dto->timesheets = [$timesheet1, $timesheet2];
        $dto->startDate = null;
        $dto->endDate = null;

        $this->firewall->filterEvent($dto);

        self::assertFalse($dto->reject->isBadEventDate());
        self::assertFalse($dto->reject->isBadEventDateInterval());
    }

    public function testTimesheetWithNullStartAtFailsValidation(): void
    {
        $dto = $this->createValidEventDto();

        $timesheet = new EventTimesheetDto();
        $timesheet->startAt = null;
        $timesheet->endAt = new DateTimeImmutable('2024-01-15 18:00:00');

        $dto->timesheets = [$timesheet];

        $this->firewall->filterEvent($dto);

        self::assertTrue($dto->reject->isBadEventDate());
    }

    public function testTimesheetWithNullEndAtFailsValidation(): void
    {
        $dto = $this->createValidEventDto();

        $timesheet = new EventTimesheetDto();
        $timesheet->startAt = new DateTimeImmutable('2024-01-15 10:00:00');
        $timesheet->endAt = null;

        $dto->timesheets = [$timesheet];

        $this->firewall->filterEvent($dto);

        self::assertTrue($dto->reject->isBadEventDate());
    }

    public function testTimesheetWithEndBeforeStartFailsValidation(): void
    {
        $dto = $this->createValidEventDto();

        $timesheet = new EventTimesheetDto();
        $timesheet->startAt = new DateTimeImmutable('2024-01-15 18:00:00');
        $timesheet->endAt = new DateTimeImmutable('2024-01-15 10:00:00'); // End before start

        $dto->timesheets = [$timesheet];

        $this->firewall->filterEvent($dto);

        self::assertTrue($dto->reject->isBadEventDateInterval());
    }

    public function testMultipleTimesheetsWithOneInvalidFailsValidation(): void
    {
        $dto = $this->createValidEventDto();

        $validTimesheet = new EventTimesheetDto();
        $validTimesheet->startAt = new DateTimeImmutable('2024-01-15 10:00:00');
        $validTimesheet->endAt = new DateTimeImmutable('2024-01-15 18:00:00');

        $invalidTimesheet = new EventTimesheetDto();
        $invalidTimesheet->startAt = new DateTimeImmutable('2024-01-20 18:00:00');
        $invalidTimesheet->endAt = new DateTimeImmutable('2024-01-20 10:00:00'); // Invalid

        $dto->timesheets = [$validTimesheet, $invalidTimesheet];

        $this->firewall->filterEvent($dto);

        self::assertTrue($dto->reject->isBadEventDateInterval());
    }

    public function testEmptyTimesheetsUsesDirectDates(): void
    {
        $dto = $this->createValidEventDto();
        $dto->timesheets = [];
        $dto->startDate = new DateTimeImmutable('2024-01-15');
        $dto->endDate = new DateTimeImmutable('2024-01-20');

        $this->firewall->filterEvent($dto);

        self::assertFalse($dto->reject->isBadEventDate());
        self::assertFalse($dto->reject->isBadEventDateInterval());
    }

    public function testEmptyTimesheetsWithInvalidDirectDatesFailsValidation(): void
    {
        $dto = $this->createValidEventDto();
        $dto->timesheets = [];
        $dto->startDate = new DateTimeImmutable('2024-01-20');
        $dto->endDate = new DateTimeImmutable('2024-01-15'); // End before start

        $this->firewall->filterEvent($dto);

        self::assertTrue($dto->reject->isBadEventDateInterval());
    }

    private function createValidEventDto(): EventDto
    {
        $dto = new EventDto();
        $dto->reject = new Reject();
        $dto->name = 'Test Event Name';
        $dto->description = 'This is a valid event description with enough characters.';
        $dto->startDate = new DateTimeImmutable('2024-01-15');
        $dto->endDate = new DateTimeImmutable('2024-01-20');

        return $dto;
    }
}
