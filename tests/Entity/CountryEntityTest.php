<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Entity;

use App\Entity\Country;
use App\Factory\CountryFactory;
use App\Tests\AppKernelTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

class CountryEntityTest extends AppKernelTestCase
{
    use ResetDatabase;

    public function testCountryCreation(): void
    {
        $country = CountryFactory::createOne([
            'id' => 'US',
            'name' => 'United States',
        ]);

        self::assertInstanceOf(Country::class, $country->object());
        self::assertEquals('US', $country->getId());
        self::assertEquals('United States', $country->getName());
    }

    public function testFranceCountry(): void
    {
        $france = CountryFactory::france()->create();

        self::assertEquals('FR', $france->getId());
        self::assertEquals('France', $france->getName());
        self::assertEquals('Paris', $france->getCapital());
    }

    public function testCountryToString(): void
    {
        $country = CountryFactory::createOne(['name' => 'Germany']);

        self::assertEquals('Germany', (string) $country->object());
    }

    public function testCountryHasDisplayName(): void
    {
        $country = CountryFactory::france()->create();

        self::assertEquals('France', $country->getDisplayName());
        self::assertEquals('en France', $country->getAtDisplayName());
    }
}
