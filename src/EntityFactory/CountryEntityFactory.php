<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityFactory;

use App\Contracts\EntityFactoryInterface;
use App\Dto\CountryDto;
use LogicException;

final class CountryEntityFactory implements EntityFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function supports(string $dtoClassName): bool
    {
        return CountryDto::class === $dtoClassName;
    }

    /**
     * {@inheritDoc}
     */
    public function create(?object $entity, object $dto): object
    {
        if (null === $entity) {
            throw new LogicException('Unable to create a new country from scratch!');
        }

        return $entity;
    }
}
