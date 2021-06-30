<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\EntityProviderInterface;
use App\Contracts\ExternalIdentifiableInterface;
use App\Contracts\ExternalIdentifiableRepositoryInterface;

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
        $externalIds = [];
        foreach ($dtos as $dto) {
            \assert($dto instanceof ExternalIdentifiableInterface);

            if (null === $dto->getExternalId()) {
                throw new \RuntimeException('Unable to find echantillon without an external ID');
            }

            $externalIds[$dto->getExternalId()] = true;
        }

        $externalIds = array_keys($externalIds);
        $entities = $this->getRepository()->findAllByExternalIds($externalIds);
        foreach ($entities as $entity) {
            $this->addEntity($entity);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addEntity(object $entity): void
    {
        \assert($entity instanceof ExternalIdentifiableInterface);

        if ($entity->getExternalId()) {
            $this->entities[$entity->getExternalId()] = $entity;
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

    abstract protected function getRepository(): ExternalIdentifiableRepositoryInterface;
}
