<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\DeletedPage;
use App\Enum\DeletedPageKind;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<DeletedPage>
 */
final class DeletedPageFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return DeletedPage::class;
    }

    protected function defaults(): array
    {
        return [
            'kind' => DeletedPageKind::Publisher,
            'identifier' => self::faker()->unique()->slug(3),
        ];
    }
}
