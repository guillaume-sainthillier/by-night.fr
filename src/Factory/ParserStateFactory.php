<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\ParserState;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ParserState>
 */
final class ParserStateFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ParserState::class;
    }

    protected function defaults(): array
    {
        return [
            'parser' => self::faker()->unique()->slug(2),
            'lastParsedAt' => new DateTimeImmutable('-1 day'),
        ];
    }
}
