<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Entity;

use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\PlaceFactory;
use App\Tests\AppKernelTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

class PlaceEntityTest extends AppKernelTestCase
{
    use ResetDatabase;

    public function testPlaceCreation(): void
    {
        $place = PlaceFactory::createOne([
            'name' => 'Le Bikini',
            'street' => 'Rue Théodore Monod',
        ]);

        self::assertInstanceOf(Place::class, $place->object());
        self::assertEquals('Le Bikini', $place->getName());
        self::assertEquals('Rue Théodore Monod', $place->getStreet());
    }

    public function testPlaceHasCity(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse]);

        self::assertNotNull($place->getCity());
        self::assertEquals('Toulouse', $place->getCity()->getName());
    }

    public function testPlaceHasCountry(): void
    {
        $france = CountryFactory::france()->create();
        $place = PlaceFactory::createOne(['country' => $france]);

        self::assertNotNull($place->getCountry());
        self::assertEquals('FR', $place->getCountry()->getId());
    }

    public function testPlaceHasCoordinates(): void
    {
        $place = PlaceFactory::createOne([
            'latitude' => 43.604652,
            'longitude' => 1.444209,
        ]);

        self::assertEquals(43.604652, $place->getLatitude());
        self::assertEquals(1.444209, $place->getLongitude());
    }

    public function testPlaceToString(): void
    {
        $place = PlaceFactory::createOne(['name' => 'Le Dynamo']);

        self::assertEquals('Le Dynamo', (string) $place->object());
    }
}
