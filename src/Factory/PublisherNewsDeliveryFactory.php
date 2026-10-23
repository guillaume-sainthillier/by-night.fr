<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\PublisherNewsDelivery;
use App\Enum\PublisherNewsEdition;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<PublisherNewsDelivery>
 */
final class PublisherNewsDeliveryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return PublisherNewsDelivery::class;
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'edition' => PublisherNewsEdition::October2026,
        ];
    }
}
