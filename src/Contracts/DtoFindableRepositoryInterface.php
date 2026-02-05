<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

/**
 * @template TDto of object
 * @template TEntity of object
 */
interface DtoFindableRepositoryInterface
{
    /**
     * Find all entities matching the given DTOs.
     *
     * @param TDto[] $dtos
     * @param bool   $eager when false, only match by fast indexed lookups (external IDs);
     *                      when true, also match by broader criteria (location, name, etc.)
     *
     * @return TEntity[]
     */
    public function findAllByDtos(array $dtos, bool $eager): array;
}
