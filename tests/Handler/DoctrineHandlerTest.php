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
use App\Dto\PlaceDto;
use App\Entity\Place;
use App\Handler\DoctrineEventHandler;
use App\Reject\Reject;
use App\Tests\ContainerTestCase;

class DoctrineHandlerTest extends ContainerTestCase
{
    protected ?DoctrineEventHandler $doctrineHandler = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctrineHandler = self::getContainer()->get(DoctrineEventHandler::class);
    }

    private function makeAsserts(PlaceDto $place, ?string $countryCode, ?string $cityName, ?string $postalCode, int $rejectReason)
    {
        $message = 'Original : ' . ($place->name ?? $place->city?->name ?? $place->postalCode);
        if (null !== $countryCode) {
            $this->assertNotNull($place->country, $message . '. Expected country : ' . $countryCode);
            $this->assertEquals($countryCode, $place->country->code, $message);
        } else {
            $this->assertNull($place->country, $message);
        }

        if (null !== $cityName) {
            $this->assertNotNull($place->city, $message . '. Expected city : ' . $cityName);
            $this->assertEquals($cityName, $place->city->name, $message);
            $this->assertNotNull($place->city->entityId, $message);
        } else {
            $this->assertNull($place->city?->entityId, $message);
        }

        if (null !== $postalCode) {
            $this->assertEquals($postalCode, $place->postalCode, $message);
        } else {
            $this->assertNull($place->postalCode, $message);
        }

        $this->assertNotNull($place->reject, $message);
        $this->assertEquals($rejectReason, $place->reject->getReason(), $message);
    }

    /**
     * @dataProvider guessEventLocationProvider()
     */
    public function testGuessEventLocation(PlaceDto $place, ?string $countryCode, ?string $cityName, ?string $postalCode, int $rejectReason)
    {
        $place->reject = new Reject();
        $this->doctrineHandler->guessEventLocation($place);

        $this->makeAsserts($place, $countryCode, $cityName, $postalCode, $rejectReason);
    }

    public function guessEventLocationProvider(): iterable
    {
        // Pas de pays
        $place = new PlaceDto();
        $place->postalCode = '99999';
        $place->city = new CityDto();
        $place->city->name = 'LoremIpsum';
        yield [
            $place,
            null,
            null,
            null,
            Reject::NO_COUNTRY_PROVIDED | Reject::VALID,
        ];
        // Mauvais CP + mauvaise ville + mauvais pays
        yield [
            (new Place())->setCodePostal('99999')->setVille('LoremIpsum')->setCountryName('LoremIpsum'),
            null,
            null,
            null,
            Reject::BAD_COUNTRY | Reject::VALID,
        ];
        // Mauvais CP + mauvaise ville + mauvais pays
        yield [
            (new Place())->setCountryName('LoremIpsum'),
            null,
            null,
            null,
            Reject::BAD_COUNTRY | Reject::VALID,
        ];
        // Mauvais CP + mauvaise ville + bon pays
        yield [
            (new Place())->setCodePostal('99999')->setVille('LoremIpsum')->setCountryName('France'),
            'FR',
            null,
            null,
            Reject::VALID,
        ];
        // Mauvais CP + bonne ville + bon pays
        yield [
            (new Place())->setCodePostal('99999')->setVille('St Germain En Laye')->setCountryName('France'),
            'FR',
            'Saint-Germain-en-Laye',
            null,
            Reject::VALID,
        ];
        // Mauvais CP + ville doublon + bon pays
        yield [
            (new Place())->setCodePostal('31000')->setVille('Roques')->setCountryName('France'),
            'FR',
            'Toulouse',
            '31000',
            Reject::VALID,
        ];
        // CP doublon + pas de ville + bon pays
        yield [
            (new Place())->setCodePostal('31470')->setCountryName('France'),
            'FR',
            null,
            null,
            Reject::VALID,
        ];
        // Pas de CP + ville doublon + bon pays
        yield [
            (new Place())->setVille('Roques')->setCountryName('France'),
            'FR',
            null,
            null,
            Reject::VALID,
        ];
        // Pas de CP + bonne ville + bon pays
        yield [
            (new Place())->setVille('toulouse')->setCountryName('France'),
            'FR',
            'Toulouse',
            null,
            Reject::VALID,
        ];
        // Monaco
        yield [
            (new Place())->setNom('Centre Hospitalier Princesse Grace')->setRue('1 Avenue Pasteur')->setCodePostal('98000')->setVille('Monaco')->setCountryName('Monaco'),
            'MC',
            'Monaco',
            '98000',
            Reject::VALID,
        ];
        // Bonnes coordonnées + mauvais pays
        yield [
            (new Place())->setNom('10, Av Princesse Grace')->setLongitude(7.4314023071828)->setLatitude(43.743460394373),
            null,
            null,
            null,
            Reject::NO_COUNTRY_PROVIDED | Reject::VALID,
        ];
    }
}
