<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\CityAlias;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<CityAlias>
 */
final class CityAliasFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return CityAlias::class;
    }

    protected function defaults(): array
    {
        return [
            'city' => CityFactory::new(),
            'name' => self::faker()->unique()->city(),
        ];
    }
}
