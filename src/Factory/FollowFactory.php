<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\Follow;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * A member following a publisher: pass `'place' => …, 'publisher' => null` for a venue.
 *
 * @extends PersistentObjectFactory<Follow>
 */
final class FollowFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Follow::class;
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'publisher' => PublisherFactory::new(),
        ];
    }
}
