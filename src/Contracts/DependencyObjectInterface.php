<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface DependencyObjectInterface
{
    /**
     * The key a DependencyCatalogue merges the DTOs of a batch by, built with ObjectKey.
     */
    public function getUniqueKey(): string;
}
