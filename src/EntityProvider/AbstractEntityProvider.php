<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\DependencyObjectInterface;
use App\Contracts\DtoFindableRepositoryInterface;
use App\Contracts\EntityProviderInterface;
use App\Contracts\ExternalIdentifiableInterface;
use App\Contracts\ExternalIdentifiablesInterface;
use App\Contracts\InternalIdentifiableInterface;
use App\Contracts\PrefixableObjectKeyInterface;
use App\Utils\ChunkUtils;

abstract class AbstractEntityProvider implements EntityProviderInterface
{
    /** @var object[] */
    protected $entities = [];

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        unset($this->entities); // Call GC
        $this->entities = [];
    }

    /**
     * {@inheritDoc}
     */
    public function prefetchEntities(array $dtos): void
    {
        $chunks = ChunkUtils::getChunksByClass($dtos);

        // Per DTO class
        foreach ($chunks as $dtoClass => $dtoChunks) {
            $repository = $this->getRepository($dtoClass);

            $entities = $repository->findAllByDtos($dtoChunks);

            foreach ($entities as $entity) {
                $this->addEntity($entity);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getEntity(object $dto): ?object
    {
        $keys = $this->getObjectKeys($dto);
        foreach ($keys as $key) {
            if (isset($this->entities[$key])) {
                return $this->entities[$key];
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function addEntity(object $entity, ?string $alias = null): void
    {
        $keys = $this->getObjectKeys($entity);
        if (null !== $alias) {
            $keys[] = $alias;
        }

        foreach ($keys as $key) {
            $this->entities[$key] = $entity;
        }
    }

    abstract protected function getRepository(string $dtoClassName): DtoFindableRepositoryInterface;

    /**
     * @return object[]
     */
    public function getEntities(): array
    {
        $uniqueEntities = [];
        foreach ($this->entities as $entity) {
            if (!\in_array($entity, $uniqueEntities, true)) {
                $uniqueEntities[] = $entity;
            }
        }

        return $uniqueEntities;
    }

    public function getObjectKeys(object $object): array
    {
        $keys = [];
        if ($object instanceof ExternalIdentifiablesInterface || $object instanceof ExternalIdentifiableInterface) {
            /** @var iterable<ExternalIdentifiableInterface> $externalIdentifiables */
            $externalIdentifiables = $object instanceof ExternalIdentifiablesInterface
                ? $object
                : [$object];

            foreach ($externalIdentifiables as $externalIdentifiable) {
                if (null === $externalIdentifiable->getExternalOrigin() || null === $externalIdentifiable->getExternalId()) {
                    continue;
                }

                $key = sprintf(
                    'external-%s-%s',
                    $externalIdentifiable->getExternalId(),
                    $externalIdentifiable->getExternalOrigin()
                );

                if ($object instanceof PrefixableObjectKeyInterface) {
                    $key = sprintf(
                        '%s-%s',
                        $object->getKeyPrefix(),
                        $key,
                    );
                }

                $keys[] = $key;
            }
        }

        if ($object instanceof DependencyObjectInterface) {
            $keys[] = $object->getUniqueKey();
        }

        if ($object instanceof InternalIdentifiableInterface && $object->getInternalId()) {
            $keys[] = $object->getInternalId();
        }

        if (0 === \count($keys)) {
            $key = sprintf('spl-%s', spl_object_hash($object));
            if ($object instanceof PrefixableObjectKeyInterface) {
                $key = sprintf(
                    '%s-%s',
                    $object->getKeyPrefix(),
                    $key,
                );
            }
            $keys[] = $key;
        }

        return $keys;
    }
}
