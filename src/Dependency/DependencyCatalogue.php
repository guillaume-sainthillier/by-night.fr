<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dependency;

use App\Contracts\DependencyCatalogueInterface;
use App\Contracts\DependencyInterface;

class DependencyCatalogue implements DependencyCatalogueInterface
{
    /** @var DependencyInterface[] */
    private $dependencies = [];

    public function __construct(array $dependencies = [])
    {
        foreach ($dependencies as $dependency) {
            $this->add($dependency);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function has(object $object): bool
    {
        $key = spl_object_hash($object);

        return isset($this->dependencies[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function get(object $object): DependencyInterface
    {
        $key = spl_object_hash($object);
        if (!isset($this->dependencies[$key])) {
            throw new \RuntimeException('Given dependency is not found');
        }

        return $this->dependencies[$key];
    }

    /**
     * {@inheritDoc}
     */
    public function add(DependencyInterface $dependency): void
    {
        $key = spl_object_hash($dependency->getObject());
        $this->dependencies[$key] = $dependency;
    }

    /**
     * {@inheritDoc}
     */
    public function addCatalogue(DependencyCatalogueInterface $catalogue): void
    {
        foreach ($catalogue->all() as $dependency) {
            $this->add($dependency);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return array_values($this->dependencies);
    }

    /**
     * {@inheritDoc}
     */
    public function objects(): array
    {
        return array_map(function (DependencyInterface $dependency) {
            return $dependency->getObject();
        }, $this->all());
    }

    public function clear(): void
    {
        unset($this->dependencies); //Call GC
        $this->dependencies = [];
    }
}
