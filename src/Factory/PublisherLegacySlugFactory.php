<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\PublisherLegacySlug;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<PublisherLegacySlug>
 */
final class PublisherLegacySlugFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return PublisherLegacySlug::class;
    }

    protected function defaults(): array
    {
        return [
            'publisher' => PublisherFactory::new(),
            'slug' => self::faker()->unique()->slug(3),
        ];
    }
}
