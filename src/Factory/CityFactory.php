<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\City;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<City>
 */
final class CityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return City::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => self::faker()->unique()->numberBetween(1, 999999),
            'name' => self::faker()->city(),
            'latitude' => self::faker()->latitude(),
            'longitude' => self::faker()->longitude(),
            'population' => self::faker()->numberBetween(1000, 1000000),
            'country' => CountryFactory::new(),
        ];
    }

    public static function toulouse(): self
    {
        return self::new([
            'id' => 1,
            'name' => 'Toulouse',
            'latitude' => 43.604652,
            'longitude' => 1.444209,
            'population' => 471941,
            'country' => CountryFactory::france(),
        ]);
    }
}
