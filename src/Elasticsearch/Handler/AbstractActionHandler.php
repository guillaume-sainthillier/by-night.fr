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
use App\Elasticsearch\Message\ClassifyEvents;
use App\Elasticsearch\Message\DocumentsAction;
use App\Elasticsearch\Message\InsertManyDocuments;
use App\Elasticsearch\Message\ReplaceManyDocuments;
use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

abstract class AbstractActionHandler
{
    /** How long a document takes to show in the searches: the refresh_interval of the event index, and a margin */
    private const int CLASSIFY_DELAY_MS = 10_000;

    public function __construct(
        protected PersisterRegistry $registry,
        protected EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
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
     * Finds the agenda types of the events just written, once the searches see their documents.
     */
    protected function classify(InsertManyDocuments|ReplaceManyDocuments $action): void
    {
        if (Event::class === $action->getEntityClass()) {
            $ids = array_values(array_map(intval(...), $action->getEntityIds()));
            $this->messageBus->dispatch(new ClassifyEvents($ids, recount: $action->isChangedOnSite()), [new DelayStamp(self::CLASSIFY_DELAY_MS)]);
        }
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
