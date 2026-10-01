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
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Country>
 */
final class CountryFactory extends PersistentObjectFactory
{
    /**
     * Codes that tests pin: the france(), switzerland() and belgium() states, and the ['id' => …]
     * overrides of tests/. Add a code here when a test starts pinning a new one.
     */
    private const array PINNED_CODES = ['BE', 'CH', 'DE', 'FR', 'LU', 'MC', 'RE'];

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
            // unique() only knows its own draws, not the codes tests pin, so a "don't care" country
            // (e.g. PlaceFactory's default) could still draw 'FR' next to a pinned France and
            // collide the same way: the draw skips PINNED_CODES.
            'id' => LazyValue::new(static function (): string {
                do {
                    $code = self::faker()->unique()->countryCode();
                } while (\in_array($code, self::PINNED_CODES, true));

                return $code;
            }),
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

    public static function switzerland(): self
    {
        return self::new([
            'id' => 'CH',
            'name' => 'Suisse',
            'displayName' => 'Suisse',
            'atDisplayName' => 'en Suisse',
            'capital' => 'Berne',
            'locale' => 'fr',
            'postalCodeRegex' => '^[1-9][0-9]{3}$',
        ]);
    }

    public static function belgium(): self
    {
        return self::new([
            'id' => 'BE',
            'name' => 'Belgique',
            'displayName' => 'Belgique',
            'atDisplayName' => 'en Belgique',
            'capital' => 'Bruxelles',
            'locale' => 'fr',
            'postalCodeRegex' => '^[1-9][0-9]{3}$',
        ]);
    }
}
