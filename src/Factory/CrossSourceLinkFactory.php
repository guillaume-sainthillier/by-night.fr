<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\CrossSourceLink;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<CrossSourceLink>
 */
final class CrossSourceLinkFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return CrossSourceLink::class;
    }

    protected function defaults(): array
    {
        return [
            'event' => EventFactory::new(),
            'linkedEvent' => EventFactory::new(),
        ];
    }
}
