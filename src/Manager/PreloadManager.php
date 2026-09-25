<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use Doctrine\Persistence\Proxy;

final readonly class PreloadManager
{
    public function __construct(
        private DoctrineManager $doctrineManager,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T>   $entityClass
     * @param array<int, mixed> $entityIds
     */
    public function preloadEntities(string $entityClass, array $entityIds): void
    {
        $entityIds = array_unique(array_filter($entityIds));
        if ([] === $entityIds) {
            return;
        }

        $em = $this->doctrineManager->getEntityManagerForClass($entityClass);
        $uow = $em->getUnitOfWork();
        // The identity map is keyed by the root of an inheritance: a City is stored as an AdminZone
        $rootEntityClass = $em->getClassMetadata($entityClass)->rootEntityName;
        foreach ($entityIds as $i => $entityId) {
            $entity = $uow->tryGetById($entityId, $rootEntityClass);
            if (
                false !== $entity
                && $this->isEntityLoaded($entity)
            ) {
                unset($entityIds[$i]);
            }
        }

        if ([] === $entityIds) {
            return;
        }

        // Even for a single id: find() hands back the uninitialized proxy it finds in the identity map
        // without loading it, and skips the joins a repository adds (CityRepository fetches the parent)
        $em->getRepository($entityClass)
            ->createQueryBuilder('entity')
            ->where('entity.id IN (:ids)')
            ->setParameter('ids', array_values($entityIds))
            ->getQuery()
            ->execute();
    }

    private function isEntityLoaded(object $entity): bool
    {
        if ($entity instanceof Proxy) {
            return $entity->__isInitialized();
        }

        return !$this->doctrineManager->getEntityManagerForClass($entity::class)->getClassMetadata($entity::class)->reflClass->isUninitializedLazyObject($entity);
    }
}
