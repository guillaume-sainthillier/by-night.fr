<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\DtoFactory;

use App\DtoFactory\EventDtoFactory;
use App\Entity\Event;
use App\Enum\EventStatus;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

/**
 * The edit page of the personal space turns the event into a DTO, binds the form to it and
 * hands it to the import pipeline, whose EventEntityFactory writes every DTO field back onto
 * the event. A field the DTO leaves out is therefore erased by any edit, even an untouched one.
 */
final class EventDtoFactoryTest extends AppKernelTestCase
{
    public function testTheDtoCarriesWhatTheEventKnows(): void
    {
        $event = $this->createImportedEvent();

        $dto = self::getContainer()->get(EventDtoFactory::class)->create($event);

        self::assertSame($event->getId(), $dto->entityId);
        self::assertSame('Concert de jazz', $dto->name);
        self::assertSame('<p>Un trio de jazz manouche.</p>', $dto->description);
        self::assertSame('2026-10-01', $dto->startDate?->format('Y-m-d'));
        self::assertSame('2026-10-03', $dto->endDate?->format('Y-m-d'));
        self::assertSame('De 20h à 23h', $dto->hours);
        self::assertSame('12€', $dto->prices);
        self::assertSame(EventStatus::Postponed, $dto->status);
        self::assertSame('Reporté au printemps', $dto->statusMessage);
        self::assertSame(['contact@example.com'], $dto->emailContacts);
        self::assertSame(['0102030405'], $dto->phoneContacts);
        self::assertSame(['https://example.com'], $dto->websiteContacts);
        self::assertSame('https://images.example.com/jazz.jpg', $dto->imageUrl);
        self::assertSame('Open Agenda', $dto->fromData);
        self::assertSame('https://openagenda.com/jazz', $dto->source);
        self::assertSame('oa-42', $dto->externalId);
        self::assertSame('openagenda', $dto->externalOrigin);
        self::assertSame('Le Bikini', $dto->place?->name);
        self::assertSame('Rue Théodore Monod', $dto->place->street);
        self::assertSame('place-7', $dto->place->externalId);
        self::assertSame('Ramonville-Saint-Agne', $dto->place->city?->name);
        self::assertSame('31520', $dto->place->city->postalCode);
        self::assertSame('FR', $dto->place->country?->entityId);
        self::assertSame($event->getUser()?->getId(), $dto->user?->entityId);
    }

    public function testTheDtoCarriesTheTypeOfTheEvent(): void
    {
        $dto = self::getContainer()->get(EventDtoFactory::class)->create($this->createImportedEvent());

        self::assertSame('Concert', $dto->type);
    }

    /**
     * The content hash leaves the picture out: with the version of the parser, an edit that
     * only uploads a picture would be skipped as unchanged.
     */
    public function testTheDtoLeavesTheParserVersionOutSoThatAnEditIsAlwaysJudged(): void
    {
        $dto = self::getContainer()->get(EventDtoFactory::class)->create($this->createImportedEvent());

        self::assertNull($dto->parserVersion);
    }

    public function testTheDtoCarriesOnlyTheOwnTimesheetsOfTheEvent(): void
    {
        $event = $this->createImportedEvent();
        EventTimesheetFactory::createOne([
            'event' => $event,
            'startAt' => new DateTimeImmutable('2026-10-01 20:00'),
            'endAt' => new DateTimeImmutable('2026-10-01 23:00'),
            'hours' => 'De 20h à 23h',
        ]);

        $dto = self::getContainer()->get(EventDtoFactory::class)->create($event);

        self::assertSame(
            [['2026-10-01 20:00', '2026-10-01 23:00', 'De 20h à 23h']],
            array_map(static fn ($timesheet): array => [
                $timesheet->startAt?->format('Y-m-d H:i'),
                $timesheet->endAt?->format('Y-m-d H:i'),
                $timesheet->hours,
            ], $dto->timesheets),
        );
    }

    private function createImportedEvent(): Event
    {
        $country = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);

        return EventFactory::createOne([
            'name' => 'Concert de jazz',
            'description' => '<p>Un trio de jazz manouche.</p>',
            'type' => 'Concert',
            'startDate' => new DateTimeImmutable('2026-10-01'),
            'endDate' => new DateTimeImmutable('2026-10-03'),
            'hours' => 'De 20h à 23h',
            'prices' => '12€',
            'status' => EventStatus::Postponed,
            'statusMessage' => 'Reporté au printemps',
            'mailContacts' => ['contact@example.com'],
            'phoneContacts' => ['0102030405'],
            'websiteContacts' => ['https://example.com'],
            'url' => 'https://images.example.com/jazz.jpg',
            'fromData' => 'Open Agenda',
            'source' => 'https://openagenda.com/jazz',
            'externalId' => 'oa-42',
            'externalOrigin' => 'openagenda',
            'parserVersion' => '2.1',
            'placeName' => 'Le Bikini',
            'placeStreet' => 'Rue Théodore Monod',
            'placeExternalId' => 'place-7',
            'placeCity' => 'Ramonville-Saint-Agne',
            'placePostalCode' => '31520',
            'placeCountry' => $country,
        ]);
    }
}
