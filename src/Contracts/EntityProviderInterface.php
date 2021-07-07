<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface EntityProviderInterface extends SupportsClassInterface
{
    /**
     * Generate chunks based on internal prefetching method
     *
     * @return object[][]
     */
    public function getChunks(array $dtos, int $defaultSize): array;

    /**
     * Prefetch all entities for given objects.
     */
    public function prefetchEntities(array $dtos): void;

    /**
     * Get the entities for given object.
     */
    public function getEntity(object $dto): ?object;

    /**
     * Add a new entity into prefeteched entities collection.
     */
    public function addEntity(object $entity): void;

    /**
     * Clear previously prefeteched entities collection.
     */
    public function clear(): void;
}
