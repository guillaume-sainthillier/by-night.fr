<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface EntityProviderInterface extends SupportsClassInterface
{
    /**
     * @psalm-param class-string $dtoClassName
     *
     * @return bool true if current provider supports this class name, false otherwise
     */
    public function supports(string $dtoClassName): bool;

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
    public function addEntity(object $entity, ?object $fromDto = null): void;

    /**
     * Clear previously prefeteched entities collection.
     */
    public function clear(): void;
}
