<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Resolver;

class AliasResolver
{
    private array $aliasLookup = [];

    /**
     * @psalm-template T
     *
     * @psalm-param T
     *
     * @return T|null
     */
    public function getAlias(object $object): ?object
    {
    }

    public function addAlias(object $object, object $alias)
    {
    }

    public function resetLookup(): void
    {
        unset($this->aliasLookup);
        $this->aliasLookup = [];
    }
}
