<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\ZipCity;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ZipCity>
 */
final class ZipCityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ZipCity::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->city(),
            'postalCode' => self::faker()->postcode(),
            'latitude' => self::faker()->latitude(),
            'longitude' => self::faker()->longitude(),
            'admin1Code' => self::faker()->randomNumber(2, true),
            'admin2Code' => self::faker()->randomNumber(3, true),
            'country' => CountryFactory::new(),
            'parent' => CityFactory::new(),
        ];
    }

    public static function toulouse31000(): self
    {
        return self::new([
            'id' => 1,
            'name' => 'Toulouse',
            'postalCode' => '31000',
            'latitude' => 43.604652,
            'longitude' => 1.444209,
            'admin1Code' => '76',
            'admin2Code' => '31',
            'country' => CountryFactory::france(),
            'parent' => CityFactory::toulouse(),
        ]);
    }

    public static function toulouse31500(): self
    {
        return self::new([
            'id' => 2,
            'name' => 'Toulouse',
            'postalCode' => '31500',
            'latitude' => 43.611653,
            'longitude' => 1.466397,
            'admin1Code' => '76',
            'admin2Code' => '31',
            'country' => CountryFactory::france(),
            'parent' => CityFactory::toulouse(),
        ]);
    }
}
