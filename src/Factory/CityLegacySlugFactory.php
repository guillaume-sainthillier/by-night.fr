<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\CityLegacySlug;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<CityLegacySlug>
 */
final class CityLegacySlugFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return CityLegacySlug::class;
    }

    protected function defaults(): array
    {
        return [
            'city' => CityFactory::new(),
            'slug' => self::faker()->unique()->slug(3),
        ];
    }
}
