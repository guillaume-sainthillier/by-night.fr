<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface DtoFindableRepositoryInterface
{
    /**
     * Returns entities by.
     *
     * @param object[] $dtos
     *
     * @return object[]
     */
    public function findAllByDtos(array $dtos): array;
}
