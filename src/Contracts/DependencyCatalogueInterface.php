<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface DependencyCatalogueInterface
{
    public function has(object $object): bool;

    public function get(object $object): DependencyInterface;

    /**
     * @return DependencyInterface[]
     */
    public function all(): array;

    public function clear(): void;

    /**
     * @return object[]
     */
    public function objects(): array;

    public function add(DependencyInterface $dependency): void;

    public function addCatalogue(self $catalogue): void;
}
