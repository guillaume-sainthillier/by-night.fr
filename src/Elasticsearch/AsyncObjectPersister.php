<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch;

use App\Elasticsearch\Message\DeleteManyByIdentifierDocuments;
use App\Elasticsearch\Message\DeleteManyDocuments;
use App\Elasticsearch\Message\InsertManyDocuments;
use App\Elasticsearch\Message\ReplaceManyDocuments;
use Elastica\Document;
use Elastica\Exception\BulkException;
use Elastica\Index;
use FOS\ElasticaBundle\Persister\ObjectPersister;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class AsyncObjectPersister implements ObjectPersisterInterface
{
    public function __construct(
        /** @var ObjectPersister */
        private ObjectPersisterInterface $decorated,
        private Index $index,
        private array $options,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function handlesObject($object): bool
    {
        return $this->decorated->handlesObject($object);
    }

    public function insertOne($object): void
    {
        $this->insertMany([$object]);
    }

    public function replaceOne($object): void
    {
        $this->replaceMany([$object]);
    }

    public function deleteOne($object): void
    {
        $this->deleteMany([$object]);
    }

    public function deleteById($id, $routing = false): void
    {
        $this->deleteManyByIdentifiers([$id], $routing);
    }

    public function insertMany(array $objects): void
    {
        $documents = [];
        foreach ($objects as $object) {
            $documents[] = $this->decorated->transformToElasticaDocument($object);
        }

        $message = new InsertManyDocuments($this->index->getName(), $documents);
        $this->messageBus->dispatch($message);
    }

    public function replaceMany(array $objects): void
    {
        $documents = [];
        foreach ($objects as $object) {
            $document = $this->decorated->transformToElasticaDocument($object);
            $document->setDocAsUpsert(true);
            $documents[] = $document;
        }

        $message = new ReplaceManyDocuments($this->index->getName(), $documents);
        $this->messageBus->dispatch($message);
    }

    public function deleteMany(array $objects): void
    {
        $documents = [];
        foreach ($objects as $object) {
            $documents[] = $this->decorated->transformToElasticaDocument($object);
        }

        $message = new DeleteManyDocuments($this->index->getName(), $documents);
        $this->messageBus->dispatch($message);
    }

    public function deleteManyByIdentifiers(array $identifiers, $routing = false): void
    {
        $message = new DeleteManyByIdentifierDocuments($this->index->getName(), $identifiers, $routing);
        $this->messageBus->dispatch($message);
        $this->decorated->deleteManyByIdentifiers($identifiers, $routing);
    }

    /**
     * @param Document[] $documents
     */
    public function doInsertMany(array $documents): void
    {
        try {
            $this->index->addDocuments($documents, $this->options);
        } catch (BulkException $e) {
            $this->log($e);
        }
    }

    /**
     * @param Document[] $documents
     */
    public function doReplaceMany(array $documents): void
    {
        try {
            $this->index->updateDocuments($documents, $this->options);
        } catch (BulkException $e) {
            $this->log($e);
        }
    }

    /**
     * @param Document[] $documents
     */
    public function doDeleteMany(array $documents): void
    {
        try {
            $this->index->deleteDocuments($documents);
        } catch (BulkException $e) {
            $this->log($e);
        }
    }

    /**
     * @param string[] $identifiers
     */
    public function doDeleteManyByIdentifiers(array $identifiers, string|bool $routing): void
    {
        try {
            $this->index->getClient()->deleteIds($identifiers, $this->index->getName(), $routing);
        } catch (BulkException $e) {
            $this->log($e);
        }
    }

    private function log(BulkException $e): void
    {
        $this->logger->error($e);
    }
}
