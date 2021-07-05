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
use App\Contracts\ExternalIdentifiableInterface;
use App\Contracts\ExternalIdentifiablesInterface;

abstract class AbstractEntityProvider implements EntityProviderInterface
{
    /** @var object[] */
    protected array $entities = [];

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        unset($this->entities); // Call GC
        $this->entities = [];
    }

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
        if ($entity instanceof ExternalIdentifiablesInterface) {
            $externals = $entity->getExternalIdentifiables();
        } elseif ($entity instanceof ExternalIdentifiableInterface) {
            $externals = [$entity];
        } else {
            throw new \LogicException('Unable to fetch external ids from "%s" class', \get_class($entity));
        }

        foreach ($externals as $external) {
            \assert($external instanceof ExternalIdentifiableInterface);
            $key = $this->getKey($external);

            $this->entities[$key] = $entity;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getEntity(object $dto): ?object
    {
        \assert($dto instanceof ExternalIdentifiableInterface);

        if (!isset($this->entities[$dto->getExternalId()])) {
            return null;
        }

        return $this->entities[$dto->getExternalId()];
    }

    private function getKey(ExternalIdentifiableInterface $object): string
    {
        return sprintf('%s-%s', $object->getExternalId(), $object->getExternalOrigin());
    }

    abstract protected function getRepository(): DtoFindableRepositoryInterface;
}
