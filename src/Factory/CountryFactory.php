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
use Zenstruck\Foundry\LazyValue;
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
            // Country's PK is an app-assigned 2-letter code. faker()->countryCode() is not
            // unique, so building several countries in one test occasionally minted two with the
            // same code, tripping Doctrine's EntityIdentityCollisionException — a seed-dependent
            // (therefore flaky) CI failure. unique() guarantees distinct codes; the closure keeps
            // it lazy (Foundry replaces overridden LazyValues without resolving them) so callers
            // pinning an explicit id (e.g. ['id' => 'FR']) bypass it entirely and the bounded
            // ISO-code pool is only drawn from for "don't care" countries.
            'id' => LazyValue::new(static fn (): string => self::faker()->unique()->countryCode()),
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
