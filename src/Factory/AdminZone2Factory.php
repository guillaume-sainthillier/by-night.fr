<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\AdminZone2;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<AdminZone2>
 */
final class AdminZone2Factory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return AdminZone2::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => self::faker()->unique()->numberBetween(1, 999999),
            'name' => self::faker()->unique()->city(),
            'latitude' => self::faker()->latitude(),
            'longitude' => self::faker()->longitude(),
            'population' => self::faker()->numberBetween(10000, 1000000),
            'country' => CountryFactory::new(),
        ];
    }
}
