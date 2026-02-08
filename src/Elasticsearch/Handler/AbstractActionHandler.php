<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Handler;

use App\Elasticsearch\AsyncObjectPersister;
use App\Elasticsearch\Message\DocumentsAction;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use InvalidArgumentException;

abstract class AbstractActionHandler
{
    public function __construct(
        protected PersisterRegistry $registry,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    protected function getPersister(DocumentsAction $action): AsyncObjectPersister
    {
        $indexName = $action->getIndexName();
        $persister = $this->registry->getPersister($indexName);
        if (!$persister instanceof AsyncObjectPersister) {
            throw new InvalidArgumentException(\sprintf('No async persister was registered for index "%s".', $indexName));
        }

        return $persister;
    }

    /**
     * @param class-string      $entityClass
     * @param array<string|int> $entityIds
     *
     * @return object[]
     */
    protected function fetchEntities(string $entityClass, array $entityIds): array
    {
        return $this
            ->entityManager
            ->getRepository($entityClass)
            ->createQueryBuilder('entity')
            ->where('entity.id IN (:ids)')
            ->setParameter('ids', $entityIds)
            ->getQuery()
            ->getResult();
    }
}
