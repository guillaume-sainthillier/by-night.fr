<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface EntityFactoryInterface extends SupportsClassInterface
{
    /**
     * Create or update the entity from a DTO instance.
     */
    public function create(?object $entity, object $dto): object;
}
