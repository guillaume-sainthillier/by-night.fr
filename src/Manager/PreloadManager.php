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

    public function preloadEntities(string $entityClass, array $entityIds): void
    {
        $entityIds = array_unique(array_filter($entityIds));
        if ([] === $entityIds) {
            return;
        }

        $em = $this->doctrineManager->getEntityManagerForClass($entityClass);
        $uow = $em->getUnitOfWork();
        foreach ($entityIds as $i => $entityId) {
            $entity = $uow->tryGetById($entityId, $entityClass);
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

        $entityIds = array_values($entityIds);
        $repository = $em->getRepository($entityClass);
        if (1 === \count($entityIds)) {
            // optimization for single entity preload
            $repository->find($entityIds[0]);
        } else {
            $repository
                ->createQueryBuilder('entity')
                ->where('entity.id IN (:ids)')
                ->setParameter('ids', $entityIds)
                ->getQuery()
                ->execute();
        }
    }

    private function isEntityLoaded(object $entity): bool
    {
        if ($entity instanceof Proxy) {
            return $entity->__isInitialized();
        }

        return !$this->doctrineManager->getEntityManagerForClass($entity::class)->getClassMetadata($entity::class)->reflClass->isUninitializedLazyObject($entity);
    }
}
