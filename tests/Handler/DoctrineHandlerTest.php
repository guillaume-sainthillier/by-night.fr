<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Handler;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\PlaceDto;
use App\Handler\DoctrineEventHandler;
use App\Reject\Reject;
use App\Tests\AppKernelTestCase;

class DoctrineHandlerTest extends AppKernelTestCase
{
    protected ?DoctrineEventHandler $doctrineHandler = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctrineHandler = self::getContainer()->get(DoctrineEventHandler::class);
    }

    public function guessEventLocationProvider(): iterable
    {
        // Pas de pays
        $place = new PlaceDto();
        $place->city = new CityDto();
        $place->city->postalCode = '99999';
        $place->city->name = 'LoremIpsum';
        yield [
            $place,
            null,
            null,
            null,
            Reject::NO_COUNTRY_PROVIDED | Reject::VALID,
        ];
        // Mauvais CP + mauvaise ville + mauvais pays
        $place = new PlaceDto();
        $place->city = new CityDto();
        $place->city->postalCode = '99999';
        $place->country = new CountryDto();
        $place->country->name = 'LoremIpsum';
        yield [
            $place,
            null,
            null,
            null,
            Reject::BAD_COUNTRY | Reject::VALID,
        ];
        // Mauvais CP + mauvaise ville + mauvais pays
        $place = new PlaceDto();
        $place->country = new CountryDto();
        $place->country->name = 'LoremIpsum';
        yield [
            $place,
            null,
            null,
            null,
            Reject::BAD_COUNTRY | Reject::VALID,
        ];
        // Mauvais CP + mauvaise ville + bon pays
        $place = new PlaceDto();
        $place->city = new CityDto();
        $place->city->postalCode = '99999';
        $place->city->name = 'LoremIpsum';
        $place->country = new CountryDto();
        $place->country->name = 'France';
        yield [
            $place,
            'FR',
            null,
            null,
            Reject::VALID,
        ];
        // Mauvais CP + bonne ville + bon pays
        $place = new PlaceDto();
        $place->city = new CityDto();
        $place->city->postalCode = '99999';
        $place->city->name = 'St Germain En Laye';
        $place->country = new CountryDto();
        $place->country->name = 'France';
        yield [
            $place,
            'FR',
            'Saint-Germain-en-Laye',
            null,
            Reject::VALID,
        ];
        // Mauvais CP + ville doublon + bon pays
        $place = new PlaceDto();
        $place->city = new CityDto();
        $place->city->postalCode = '31000';
        $place->city->name = 'Roques';
        $place->country = new CountryDto();
        $place->country->name = 'France';
        yield [
            $place,
            'FR',
            'Toulouse',
            '31000',
            Reject::VALID,
        ];
        // CP doublon + pas de ville + bon pays
        $place = new PlaceDto();
        $place->city = new CityDto();
        $place->city->postalCode = '31470';
        $place->country = new CountryDto();
        $place->country->name = 'France';
        yield [
            $place,
            'FR',
            null,
            null,
            Reject::VALID,
        ];
        // Pas de CP + ville doublon + bon pays
        $place = new PlaceDto();
        $place->city = new CityDto();
        $place->city->name = 'Roques';
        $place->country = new CountryDto();
        $place->country->name = 'France';
        yield [
            $place,
            'FR',
            null,
            null,
            Reject::VALID,
        ];
        // Pas de CP + bonne ville + bon pays
        $place = new PlaceDto();
        $place->city = new CityDto();
        $place->city->name = 'toulouse';
        $place->country = new CountryDto();
        $place->country->name = 'France';
        yield [
            $place,
            'FR',
            'Toulouse',
            null,
            Reject::VALID,
        ];
        // Monaco
        $place = new PlaceDto();
        $place->name = 'Centre Hospitalier Princesse Grace';
        $place->street = '1 Avenue Pasteur';
        $place->city = new CityDto();
        $place->city->postalCode = '98000';
        $place->city->name = 'Monaco';
        $place->country = new CountryDto();
        $place->country->name = 'Monaco';
        yield [
            $place,
            'MC',
            'Monaco',
            '98000',
            Reject::VALID,
        ];
        // Bonnes coordonnées + mauvais pays
        $place = new PlaceDto();
        $place->name = '10, Av Princesse Grace';
        $place->longitude = 7.4314023071828;
        $place->latitude = 43.743460394373;
        yield [
            $place,
            null,
            null,
            null,
            Reject::NO_COUNTRY_PROVIDED | Reject::VALID,
        ];
    }

    /**
     * @dataProvider guessEventLocationProvider()
     */
    public function testGuessEventLocation(PlaceDto $place, ?string $expectedCountryCode, ?string $expectedCityName, ?string $expectedCityPostalCode, int $expectedRejectReason)
    {
        $place->reject = new Reject();
        $this->doctrineHandler->guessEventLocation($place);

        $message = 'Original : ' . ($place->name ?? $place->city?->name ?? $place->city?->postalCode);
        if (null !== $expectedCountryCode) {
            self::assertNotNull($place->country, $message . '. Expected country : ' . $expectedCountryCode);
            self::assertEquals($expectedCountryCode, $place->country->code, $message);
        } else {
            self::assertNull($place->country, $message);
        }

        if (null !== $expectedCityName) {
            self::assertNotNull($place->city, $message . '. Expected city : ' . $expectedCityName);
            self::assertEquals($expectedCityName, $place->city->name, $message);
            self::assertNotNull($place->city->entityId, $message);
        } else {
            self::assertNull($place->city?->entityId, $message);
        }

        if (null !== $expectedCityPostalCode) {
            self::assertEquals($expectedCityPostalCode, $place->city?->postalCode, $message);
        } else {
            self::assertNull($place->city?->postalCode, $message);
        }

        self::assertNotNull($place->reject, $message);
        self::assertEquals($expectedRejectReason, $place->reject->getReason(), $message);
    }
}
