<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\EventTimesheetDto;
use App\Dto\PlaceDto;
use App\Dto\TagDto;
use App\Utils\EventContentHasher;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EventContentHasherTest extends TestCase
{
    private EventContentHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new EventContentHasher();
    }

    public function testIdenticalContentProducesIdenticalHash(): void
    {
        self::assertSame(
            $this->hasher->hash($this->event()),
            $this->hasher->hash($this->event()),
            'Two DTOs with the same content must hash identically.',
        );
    }

    public function testHashIsASha1(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $this->hasher->hash($this->event()));
    }

    public function testChangingContentChangesHash(): void
    {
        $base = $this->hasher->hash($this->event());

        $renamed = $this->event();
        $renamed->name = 'A different name';

        self::assertNotSame($base, $this->hasher->hash($renamed));
    }

    public function testChangingPlaceChangesHash(): void
    {
        $base = $this->hasher->hash($this->event());

        $moved = $this->event();
        $moved->place->name = 'Another venue';

        self::assertNotSame($base, $this->hasher->hash($moved));
    }

    public function testReorderingThemesKeepsHashStable(): void
    {
        $ordered = $this->event();
        $ordered->themes = [TagDto::fromString('rock'), TagDto::fromString('jazz')];

        $reordered = $this->event();
        $reordered->themes = [TagDto::fromString('jazz'), TagDto::fromString('rock')];

        self::assertSame(
            $this->hasher->hash($ordered),
            $this->hasher->hash($reordered),
            'Theme order is not meaningful, so it must not affect the fingerprint.',
        );
    }

    public function testExternalUpdatedAtAloneDoesNotChangeHash(): void
    {
        $base = $this->hasher->hash($this->event());

        $touched = $this->event();
        $touched->externalUpdatedAt = new DateTimeImmutable('2030-01-01 00:00:00');

        self::assertSame(
            $base,
            $this->hasher->hash($touched),
            'A bumped source timestamp with unchanged content must not trigger a re-import.',
        );
    }

    public function testChangingTimesheetChangesHash(): void
    {
        $base = $this->hasher->hash($this->event());

        $rescheduled = $this->event();
        $rescheduled->timesheets[0]->hours = 'À 21h00';

        self::assertNotSame($base, $this->hasher->hash($rescheduled));
    }

    private function event(): EventDto
    {
        $event = new EventDto();
        $event->externalId = 'evt-1';
        $event->externalOrigin = 'openagenda';
        $event->name = 'Concert';
        $event->description = 'A nice concert in town';
        $event->startDate = new DateTimeImmutable('2026-07-25 20:00:00');
        $event->endDate = new DateTimeImmutable('2026-07-25 23:00:00');
        $event->hours = 'À 20h00';
        $event->prices = '15€';
        $event->websiteContacts = ['https://example.com'];
        $event->themes = [TagDto::fromString('rock'), TagDto::fromString('jazz')];

        $timesheet = new EventTimesheetDto();
        $timesheet->startAt = new DateTimeImmutable('2026-07-25 20:00:00');
        $timesheet->endAt = new DateTimeImmutable('2026-07-25 23:00:00');
        $timesheet->hours = 'À 20h00';
        $event->timesheets = [$timesheet];

        $place = new PlaceDto();
        $place->name = 'Le Bikini';
        $place->externalId = 'place-1';

        $city = new CityDto();
        $city->name = 'Toulouse';
        $city->postalCode = '31000';

        $country = new CountryDto();
        $country->code = 'FR';

        $city->country = $country;
        $place->city = $city;
        $place->country = $country;
        $event->place = $place;

        return $event;
    }
}
