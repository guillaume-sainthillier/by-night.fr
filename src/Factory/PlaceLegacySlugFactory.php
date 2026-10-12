<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\PlaceLegacySlug;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<PlaceLegacySlug>
 */
final class PlaceLegacySlugFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return PlaceLegacySlug::class;
    }

    protected function defaults(): array
    {
        $country = CountryFactory::new();

        return [
            'place' => PlaceFactory::new(),
            'slug' => self::faker()->unique()->slug(3),
            'city' => CityFactory::new(['country' => $country]),
            'country' => $country,
        ];
    }
}
