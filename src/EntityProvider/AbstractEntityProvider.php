<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\DtoFindableRepositoryInterface;
use App\Contracts\EntityProviderInterface;

abstract class AbstractEntityProvider implements EntityProviderInterface
{
    /** @var object[] */
    protected array $entities = [];

    /**
     * {@inheritDoc}
     */
    public function getChunks(array $dtos, int $defaultSize): array
    {
        return array_chunk($dtos, $defaultSize, true);
    }

    /**
     * {@inheritDoc}
     */
    public function prefetchEntities(array $dtos): void
    {
        $entities = $this->getRepository()->findAllByDtos($dtos);
        foreach ($entities as $entity) {
            $this->addEntity($entity);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addEntity(object $entity): void
    {
        $this->entities[] = $entity;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        unset($this->entities); // Call GC
        $this->entities = [];
    }

    abstract protected function getRepository(): DtoFindableRepositoryInterface;
}
