<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\Country;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Country>
 */
final class CountryFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Country::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => self::faker()->countryCode(),
            'name' => self::faker()->country(),
            'displayName' => self::faker()->country(),
            'atDisplayName' => 'à ' . self::faker()->country(),
            'capital' => self::faker()->city(),
            'locale' => 'fr',
        ];
    }

    public static function france(): self
    {
        return self::new([
            'id' => 'FR',
            'name' => 'France',
            'displayName' => 'France',
            'atDisplayName' => 'en France',
            'capital' => 'Paris',
            'locale' => 'fr',
            'postalCodeRegex' => '^[0-9]{5}$',
        ]);
    }
}
