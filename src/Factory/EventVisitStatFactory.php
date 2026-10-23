<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\EventVisitStat;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<EventVisitStat>
 */
final class EventVisitStatFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return EventVisitStat::class;
    }

    protected function defaults(): array
    {
        return [
            'event' => EventFactory::new(),
            'day' => new DateTimeImmutable('yesterday'),
            'visitors' => self::faker()->numberBetween(0, 100),
            'ticketClicks' => self::faker()->numberBetween(0, 10),
        ];
    }
}
