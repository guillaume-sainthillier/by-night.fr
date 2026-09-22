<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\ParserHistory;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ParserHistory>
 */
final class ParserHistoryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ParserHistory::class;
    }

    protected function defaults(): array
    {
        return [
            'startDate' => new DateTimeImmutable('-1 minute'),
            'endDate' => new DateTimeImmutable(),
            'fromData' => ['Open Agenda'],
        ];
    }
}
