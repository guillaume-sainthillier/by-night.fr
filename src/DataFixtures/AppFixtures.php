<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\DataFixtures;

use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\PlaceFactory;
use App\Factory\ZipCityFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create France
        $france = CountryFactory::france()->create();

        // Create Toulouse city
        $toulouse = CityFactory::toulouse()->create();

        // Create Toulouse zip codes
        ZipCityFactory::toulouse31000()->create();
        ZipCityFactory::toulouse31500()->create();

        // Create some places in Toulouse
        PlaceFactory::createMany(10, [
            'city' => $toulouse,
            'country' => $france,
        ]);

        $manager->flush();
    }
}
