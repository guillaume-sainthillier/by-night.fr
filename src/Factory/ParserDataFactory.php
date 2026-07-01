<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\ParserData;
use App\Import\Firewall;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ParserData>
 */
final class ParserDataFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ParserData::class;
    }

    protected function defaults(): array
    {
        return [
            'externalId' => self::faker()->unique()->uuid(),
            'externalOrigin' => 'openagenda',
            'firewallVersion' => Firewall::VERSION,
            'parserVersion' => '1.0',
            'contentHash' => sha1(self::faker()->unique()->sentence()),
        ];
    }
}
