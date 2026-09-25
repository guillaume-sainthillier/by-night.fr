<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\BatchResetInterface;
use App\Contracts\DependencyObjectInterface;
use App\Contracts\DtoFindableRepositoryInterface;
use App\Contracts\EntityProviderInterface;
use App\Contracts\ExternalIdentifiableInterface;
use App\Contracts\ExternalIdentifiablesInterface;
use App\Contracts\InternalIdentifiableInterface;
use App\Contracts\PrefixableObjectKeyInterface;
use App\Utils\ChunkUtils;
use App\Utils\ObjectKey;

/**
 * @template TDto of object
 * @template TEntity of object
 *
 * @implements EntityProviderInterface<TDto, TEntity>
 */
abstract class AbstractEntityProvider implements EntityProviderInterface, BatchResetInterface
{
    /** @var array<string, TEntity> */
    protected array $entities = [];

    /**
     * Distinct prefetched entities, keyed by spl_object_id and kept in sync with
     * $entities so getEntities() does not rebuild the list on every lookup.
     *
     * @var array<int, TEntity>
     */
    private array $uniqueEntities = [];

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        $this->entities = [];
        $this->uniqueEntities = [];
    }

    /**
     * Prefetched entities must not outlive the identity map: once the EntityManager
     * is cleared (end of a chunk, or rollback of a failed batch) they are detached, and
     * handing one out to the next batch would make Doctrine treat it as a new entity.
     */
    public function batchReset(): void
    {
        $this->clear();
    }

    /**
     * {@inheritDoc}
     */
    public function prefetchEntities(array $dtos, bool $eager): void
    {
        // Only places have a broader (eager) lookup strategy, and PlaceEntityProvider
        // overrides this method to implement it. For every other entity the eager pass
        // would re-run the exact same indexed query as the first one, so skip it.
        if ($eager) {
            return;
        }

        $chunks = ChunkUtils::getChunksByClass($dtos);

        // Per DTO class
        foreach ($chunks as $dtoClass => $dtoChunks) {
            $repository = $this->getRepository($dtoClass);

            $entities = $repository->findAllByDtos($dtoChunks, $eager);
            foreach ($entities as $i => $entity) {
                $this->addEntity($entity);
                unset($entities[$i]);
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
    public function addEntity(object $entity, ?object $fromDto = null): void
    {
        $keys = $this->getObjectKeys($entity);
        if (null !== $fromDto) {
            $keys = array_unique([
                ...$keys,
                ...$this->getObjectKeys($fromDto),
            ]);
        }

        foreach ($keys as $key) {
            $this->entities[$key] = $entity;
        }

        $this->uniqueEntities[spl_object_id($entity)] = $entity;
    }

    abstract protected function getRepository(string $dtoClassName): DtoFindableRepositoryInterface;

    /**
     * @return TEntity[]
     */
    public function getEntities(): array
    {
        return array_values($this->uniqueEntities);
    }

    /**
     * Every key the object is known under, see ObjectKey: a DTO finds the entity filed
     * under any of its own keys.
     *
     * @return list<string>
     */
    public function getObjectKeys(object $object): array
    {
        $prefix = $object instanceof PrefixableObjectKeyInterface ? $object->getKeyPrefix() : '';
        $keys = [];

        if ($object instanceof InternalIdentifiableInterface && null !== $internalId = $object->getInternalId()) {
            $keys[] = $internalId;
        }

        if ($object instanceof ExternalIdentifiablesInterface || $object instanceof ExternalIdentifiableInterface) {
            /** @var iterable<ExternalIdentifiableInterface> $externalIdentifiables */
            $externalIdentifiables = $object instanceof ExternalIdentifiablesInterface
                ? $object->getExternalIdentifiables()
                : [$object];

            foreach ($externalIdentifiables as $externalIdentifiable) {
                $origin = $externalIdentifiable->getExternalOrigin();
                $id = $externalIdentifiable->getExternalId();
                if (null !== $origin && null !== $id) {
                    $keys[] = ObjectKey::external($prefix, $origin, $id);
                }
            }
        }

        if ($object instanceof DependencyObjectInterface) {
            $keys[] = $object->getUniqueKey();
        }

        if ([] === $keys) {
            $keys[] = ObjectKey::transient($prefix, $object);
        }

        return array_values(array_unique($keys));
    }
}
