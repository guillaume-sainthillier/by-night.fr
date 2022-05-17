<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dependency;

use App\Contracts\DependencyCatalogueInterface;
use App\Contracts\DependencyInterface;
use App\Contracts\DependencyObjectInterface;

class DependencyCatalogue implements DependencyCatalogueInterface
{
    /** @var DependencyInterface[] */
    private array $dependencies = [];

    public function __construct(array $dependencies = [])
    {
        foreach ($dependencies as $dependency) {
            $this->add($dependency);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function has(DependencyObjectInterface $object): bool
    {
        $key = $object->getUniqueKey();

        return isset($this->dependencies[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function get(DependencyObjectInterface $object): DependencyInterface
    {
        $key = $object->getUniqueKey();
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
        if ($this->has($dependency->getObject())) {
            return;
        }

        $key = $dependency->getObject()->getUniqueKey();
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
        unset($this->dependencies); // Call GC
        $this->dependencies = [];
    }
}
