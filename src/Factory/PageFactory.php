<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\Page;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Page>
 */
final class PageFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Page::class;
    }

    protected function defaults(): array
    {
        // The slug is left out on purpose: Gedmo generates it from the title on persist,
        // which is exactly what the tests want to exercise.
        return [
            'title' => self::faker()->unique()->sentence(3),
            'content' => '<p>' . self::faker()->paragraph() . '</p>',
        ];
    }
}
