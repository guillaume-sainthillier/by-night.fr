<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch;

use App\Contracts\MultipleEagerLoaderInterface;
use App\Elasticsearch\Message\DeleteManyDocumentsByIdentifiers;
use App\Elasticsearch\Message\InsertManyDocuments;
use App\Elasticsearch\Message\ReplaceManyDocuments;
use App\Messenger\TransactionalMessageDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Index;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;

final readonly class AsyncObjectPersister implements ObjectPersisterInterface
{
    private string $indexName;

    public function __construct(
        private ObjectPersisterInterface $decorated,
        Index $index,
        /** @var class-string */
        private string $entityClass,
        private ElasticaMode $elasticaMode,
        private TransactionalMessageDispatcher $messageDispatcher,
        private EntityManagerInterface $entityManager,
    ) {
        $this->indexName = $index->getName();
    }

    public function handlesObject(object $object): bool
    {
        return $this->decorated->handlesObject($object);
    }

    public function insertOne(object $object): void
    {
        $this->insertMany([$object]);
    }

    public function replaceOne(object $object): void
    {
        $this->replaceMany([$object]);
    }

    public function deleteOne(object $object): void
    {
        $this->deleteMany([$object]);
    }

    public function deleteById(string $id, string|bool $routing = false): void
    {
        $this->deleteManyByIdentifiers([$id], $routing);
    }

    public function insertMany(array $objects): void
    {
        if ($this->elasticaMode->isSynchronous()) {
            $this->doInsertMany($objects);

            return;
        }

        $entityIds = array_map(static fn (object $object): mixed => $object->getId(), $objects);
        $this->messageDispatcher->dispatch(new InsertManyDocuments($this->indexName, $this->entityClass, $entityIds));
    }

    public function replaceMany(array $objects): void
    {
        if ($this->elasticaMode->isSynchronous()) {
            $this->doReplaceMany($objects);

            return;
        }

        $entityIds = array_map(static fn (object $object): mixed => $object->getId(), $objects);
        $this->messageDispatcher->dispatch(new ReplaceManyDocuments($this->indexName, $this->entityClass, $entityIds));
    }

    public function deleteMany(array $objects): void
    {
        if ($this->elasticaMode->isSynchronous()) {
            $this->decorated->deleteMany($objects);

            return;
        }

        $identifiers = array_map(static fn (object $object): mixed => $object->getId(), $objects);
        $this->deleteManyByIdentifiers($identifiers);
    }

    public function deleteManyByIdentifiers(array $identifiers, $routing = false): void
    {
        if ($this->elasticaMode->isSynchronous()) {
            $this->decorated->deleteManyByIdentifiers($identifiers, $routing);

            return;
        }

        $this->messageDispatcher->dispatch(new DeleteManyDocumentsByIdentifiers($this->indexName, $identifiers, $routing));
    }

    /**
     * @param object[] $entities
     */
    public function doInsertMany(array $entities): void
    {
        $this->eagerLoad($entities);
        $this->decorated->insertMany($entities);
    }

    /**
     * @param object[] $entities
     */
    public function doReplaceMany(array $entities): void
    {
        $this->eagerLoad($entities);
        // Indexed whole: FOS's replaceMany() sends partial updates merged into the stored document,
        // and the serializer leaves null fields out, so a category, a city or a description
        // removed from the event stayed searchable. Indexing also creates a missing document.
        $this->decorated->insertMany($entities);
    }

    /**
     * @param array<string|int> $identifiers
     */
    public function doDeleteManyByIdentifiers(array $identifiers, string|bool $routing): void
    {
        $this->decorated->deleteManyByIdentifiers($identifiers, $routing);
    }

    /**
     * @param object[] $entities
     */
    private function eagerLoad(array $entities): void
    {
        if ([] === $entities) {
            return;
        }

        $repository = $this->entityManager->getRepository($this->entityClass);
        if ($repository instanceof MultipleEagerLoaderInterface) {
            $repository->loadAllEager($entities, ['view' => 'elasticsearch:document']);
        }
    }
}
