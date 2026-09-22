<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\UserEvent;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * An event in a member's calendar (going or wish list).
 *
 * @extends PersistentObjectFactory<UserEvent>
 */
final class UserEventFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return UserEvent::class;
    }

    protected function defaults(): array
    {
        return [
            'going' => true,
            'wish' => false,
            'user' => UserFactory::new(),
            'event' => EventFactory::new(),
        ];
    }
}
