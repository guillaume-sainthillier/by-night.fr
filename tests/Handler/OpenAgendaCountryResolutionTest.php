<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Handler;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Handler\DoctrineEventHandler;
use App\Tests\AppKernelTestCase;
use DateTime;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

final class OpenAgendaCountryResolutionTest extends AppKernelTestCase
{
    private DoctrineEventHandler $handler;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = self::getContainer()->get(DoctrineEventHandler::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideCountryCodes(): iterable
    {
        yield 'canonical' => ['FR'];
        yield 'lowercase' => ['fr'];
        yield 'padded' => [' FR '];
    }

    #[DataProvider('provideCountryCodes')]
    public function testEveryFrenchPlaceOfALargeBatchGetsItsCountry(string $code): void
    {
        // 130 events over three import chunks, 45 distinct locations, a mix of known and
        // unknown cities: the shape of a real OpenAgenda batch.
        $france = CountryFactory::france()->create();
        CityFactory::createOne(['name' => 'Toulouse', 'country' => $france]);
        CityFactory::createOne(['name' => 'Lyon', 'country' => $france]);

        $dtos = [];
        for ($i = 1; $i <= 130; ++$i) {
            $dtos[] = self::openAgendaEvent($i, $code);
        }

        $this->handler->handleMany($dtos);

        self::assertSame(130, EventFactory::count(['externalOrigin' => 'openagenda']), 'Every event must be imported, whatever the spelling of its country code');
        self::assertGreaterThan(0, PlaceFactory::count());

        $countryless = PlaceFactory::findBy(['country' => null]);
        $names = array_map(static fn ($p) => $p->getName() . ' [' . $p->getCityName() . ']', $countryless);
        self::assertSame([], $names, 'Every place with countryCode FR must resolve the France row');
        self::assertSame(0, EventFactory::count(['placeCountry' => null]), 'The denormalised country follows on every event');
    }

    private static function openAgendaEvent(int $i, string $code): EventDto
    {
        $country = new CountryDto();
        $country->code = $code;

        $city = new CityDto();
        $city->name = match ($i % 4) {
            0 => 'Toulouse',
            1 => 'Lyon',
            default => \sprintf('Village %d', $i),
        };
        $city->postalCode = \sprintf('%05d', 31000 + $i);
        $city->country = $country;

        $place = new PlaceDto();
        $place->name = \sprintf('Salle %d', $i % 45);
        $place->externalId = (string) (1000 + ($i % 45));
        $place->externalOrigin = 'openagenda';
        $place->latitude = 43.6;
        $place->longitude = 1.44;
        $place->city = $city;
        $place->country = $country;

        $event = new EventDto();
        $event->name = \sprintf('Événement %d', $i);
        $event->description = \sprintf('Description assez longue de l\'événement numéro %d pour passer le firewall.', $i);
        $event->startDate = new DateTime(\sprintf('+%d days', 1 + $i % 20));
        $event->endDate = new DateTime(\sprintf('+%d days +2 hours', 1 + $i % 20));
        $event->externalId = (string) (5000 + $i);
        $event->externalOrigin = 'openagenda';
        $event->parserVersion = '1.0';
        $event->place = $place;

        return $event;
    }
}
