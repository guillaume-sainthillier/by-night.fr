<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dependency;

use App\Contracts\DependencyInterface;

class Dependency implements DependencyInterface
{
    /** @var object */
    private $object;

    /** @var bool */
    private $isOptional;

    public function __construct(object $object, bool $isOptional = true)
    {
        $this->object = $object;
        $this->isOptional = $isOptional;
    }

    /**
     * {@inheritDoc}
     */
    public function getObject(): object
    {
        return $this->object;
    }

    /**
     * {@inheritDoc}
     */
    public function isOptional(): bool
    {
        return $this->isOptional;
    }
}
