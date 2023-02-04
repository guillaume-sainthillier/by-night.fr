<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface DependencyCatalogueInterface
{
    public function has(DependencyObjectInterface $object): bool;

    public function get(DependencyObjectInterface $object): DependencyInterface;

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

    public function hasAliases(DependencyObjectInterface $object): bool;

    public function getAliases(DependencyObjectInterface $object): array;
}
