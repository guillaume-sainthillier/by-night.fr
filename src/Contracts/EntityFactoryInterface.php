<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface EntityFactoryInterface
{
    /**
     * @return bool true if current factory supports this class name, false otherwise
     */
    public function supports(string $dtoClassName): bool;

    /**
     * Create or update the entity from a DTO instance.
     */
    public function create(?object $entity, object $dto): object;
}
