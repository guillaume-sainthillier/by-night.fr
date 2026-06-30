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
use App\Dto\PlaceDto;
use App\Entity\ParserData;
use App\Reject\Reject;
use App\Tests\AppKernelTestCase;
use App\Utils\EventContentHasher;
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
        $hasher = new EventContentHasher();

        // Événement inchangé (même version, même empreinte) -> NO_NEED_TO_UPDATE
        $event = $this->explorationEvent();
        $exploration = new ParserData()
            ->setReject(new Reject())
            ->setFirewallVersion(Firewall::VERSION)
            ->setParserVersion($event->parserVersion)
            ->setContentHash($hasher->hash($event));

        $this->firewall->filterEventExploration($exploration, $event);
        $reject = $exploration->getReject();
        self::assertNotNull($reject);
        self::assertEquals(Reject::NO_NEED_TO_UPDATE | Reject::VALID, $reject->getReason());
        self::assertEquals(Firewall::VERSION, $exploration->getFirewallVersion());

        // Le contenu a changé (même version) -> on lève NO_NEED_TO_UPDATE
        $event = $this->explorationEvent();
        $exploration = new ParserData()
            ->setReject(new Reject()->addReason(Reject::NO_NEED_TO_UPDATE))
            ->setFirewallVersion(Firewall::VERSION)
            ->setParserVersion($event->parserVersion)
            ->setContentHash($hasher->hash($event));
        $event->name = 'A brand new title'; // mute le contenu APRÈS avoir figé l'empreinte stockée

        $this->firewall->filterEventExploration($exploration, $event);
        $reject = $exploration->getReject();
        self::assertNotNull($reject);
        self::assertEquals(Reject::VALID, $reject->getReason());
        self::assertEquals(Firewall::VERSION, $exploration->getFirewallVersion());

        // La version du firewall a changé : on réévalue MÊME si le contenu est identique,
        // car le guard de publication republie déjà ces événements — le consommateur ne
        // doit pas les écarter silencieusement comme « rien à mettre à jour ».
        $event = $this->explorationEvent();
        $exploration = new ParserData()
            ->setReject(new Reject()->addReason(Reject::NO_NEED_TO_UPDATE))
            ->setFirewallVersion('old version')
            ->setParserVersion($event->parserVersion)
            ->setContentHash($hasher->hash($event));

        $this->firewall->filterEventExploration($exploration, $event);
        $reject = $exploration->getReject();
        self::assertNotNull($reject);
        self::assertEquals(Reject::VALID, $reject->getReason());
        self::assertEquals(Firewall::VERSION, $exploration->getFirewallVersion());

        // La version du firewall a changé et l'événement n'était pas valide avant -> valide
        $event = $this->explorationEvent();
        $exploration = new ParserData()
            ->setReject(new Reject()->addReason(Reject::BAD_PLACE_LOCATION))
            ->setFirewallVersion('old version')
            ->setParserVersion($event->parserVersion);

        $this->firewall->filterEventExploration($exploration, $event);
        $reject = $exploration->getReject();
        self::assertNotNull($reject);
        self::assertEquals(Reject::VALID, $reject->getReason());
        self::assertEquals(Firewall::VERSION, $exploration->getFirewallVersion());

        // L'événement ne doit pas être mis à jour car son créateur l'a supprimé
        $event = $this->explorationEvent('new version');
        $exploration = new ParserData()
            ->setReject(new Reject()->addReason(Reject::EVENT_DELETED))
            ->setFirewallVersion('old version')
            ->setParserVersion('1.0');

        $this->firewall->filterEventExploration($exploration, $event);
        $reject = $exploration->getReject();
        self::assertNotNull($reject);
        self::assertEquals(Reject::EVENT_DELETED | Reject::VALID, $reject->getReason());
        self::assertEquals('old version', $exploration->getFirewallVersion());
    }

    public function testIsEventDtoValid(): void
    {
        // Valid event with a valid place
        $dto = new EventDto();
        $dto->reject = new Reject();
        $dto->place = new PlaceDto();
        $dto->place->reject = new Reject();
        self::assertTrue($this->firewall->isEventDtoValid($dto));

        // Rejected, place-less event must NOT be considered valid
        $dto = new EventDto();
        $dto->reject = new Reject()->addReason(Reject::NO_PLACE_PROVIDED);
        $dto->place = null;
        self::assertFalse($this->firewall->isEventDtoValid($dto), 'A place-less rejected event must be filtered out.');

        // Valid event reject but invalid place reject
        $dto = new EventDto();
        $dto->reject = new Reject();
        $dto->place = new PlaceDto();
        $dto->place->reject = new Reject()->addReason(Reject::BAD_PLACE_NAME);
        self::assertFalse($this->firewall->isEventDtoValid($dto));
    }

    private function explorationEvent(string $parserVersion = '1.0'): EventDto
    {
        $event = new EventDto();
        $event->reject = new Reject();
        $event->parserVersion = $parserVersion;
        $event->name = 'Concert';
        $event->description = 'A nice concert in town';

        return $event;
    }

    public function testFilterEventStoresContentHashOnNewExploration(): void
    {
        $dto = $this->createValidEventDto();
        $dto->externalId = 'evt-hash-new';
        $dto->externalOrigin = 'openagenda';

        $this->firewall->filterEvent($dto);

        $exploration = $this->firewall->getExploration('evt-hash-new');
        self::assertNotNull($exploration);
        self::assertSame(
            (new EventContentHasher())->hash($dto),
            $exploration->getContentHash(),
            'A freshly observed event must store its content fingerprint for the next run to compare.',
        );
    }

    public function testFilterEventExplorationRefreshesContentHash(): void
    {
        $exploration = new ParserData()
            ->setReject(new Reject()->addReason(Reject::NO_NEED_TO_UPDATE))
            ->setLastUpdated(new DateTimeImmutable())
            ->setContentHash('staleeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee');

        $event = new EventDto();
        $event->name = 'A changed title';

        $this->firewall->filterEventExploration($exploration, $event);

        self::assertSame(
            (new EventContentHasher())->hash($event),
            $exploration->getContentHash(),
            'Re-observing an existing exploration must refresh its fingerprint, even on reject paths.',
        );
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
