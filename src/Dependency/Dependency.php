<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dependency;

use App\Contracts\DependencyInterface;
use App\Contracts\DependencyObjectInterface;

class Dependency implements DependencyInterface
{
    public function __construct(private DependencyObjectInterface $object, private bool $isReference = true)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getObject(): DependencyObjectInterface
    {
        return $this->object;
    }

    /**
     * {@inheritDoc}
     */
    public function isReference(): bool
    {
        return $this->isReference;
    }
}
