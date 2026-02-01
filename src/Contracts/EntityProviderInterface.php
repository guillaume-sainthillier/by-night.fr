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

/**
 * @template TDto of object
 * @template TEntity of object
 */
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
     *
     * @param TDto[] $dtos
     */
    public function prefetchEntities(array $dtos): void;

    /**
     * Get the entity for given DTO.
     *
     * @param TDto $dto
     *
     * @return TEntity|null
     */
    public function getEntity(object $dto): ?object;

    /**
     * Add a new entity into prefetched entities collection.
     *
     * @param TEntity   $entity
     * @param TDto|null $fromDto
     */
    public function addEntity(object $entity, ?object $fromDto = null): void;

    /**
     * Clear previously prefetched entities collection.
     */
    public function clear(): void;
}
