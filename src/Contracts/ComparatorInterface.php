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
interface ComparatorInterface extends SupportsObjectInterface
{
    /**
     * @param iterable<TEntity> $entities
     * @param TDto              $dto
     *
     * @return MatchingInterface<TEntity>|null
     */
    public function getMostMatching(iterable $entities, object $dto): ?MatchingInterface;

    /**
     * @param TEntity $entity
     * @param TDto    $dto
     *
     * @return MatchingInterface<TEntity>|null
     */
    public function getMatching(object $entity, object $dto): ?MatchingInterface;
}
