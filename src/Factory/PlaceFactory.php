<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\Place;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Place>
 */
final class PlaceFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Place::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->company(),
            'street' => self::faker()->streetAddress(),
            'latitude' => self::faker()->latitude(),
            'longitude' => self::faker()->longitude(),
            'city' => CityFactory::new(),
            'country' => CountryFactory::new(),
        ];
    }
}
