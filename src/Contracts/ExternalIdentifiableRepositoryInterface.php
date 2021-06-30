<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface ExternalIdentifiableRepositoryInterface
{
    /**
     * Returns entities by.
     *
     * @param string[] $externalIds
     *
     * @return object[]
     */
    public function findAllByExternalIds(array $externalIds): array;
}
