<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Elasticsearch;

use App\Elasticsearch\AsyncObjectPersister;
use App\Elasticsearch\ElasticaMode;
use App\Entity\Event;
use App\Factory\EventFactory;
use App\Messenger\TransactionalMessageDispatcher;
use App\Tests\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Bulk;
use Elastica\Bulk\Action;
use Elastica\Client;
use Elastica\Document;
use Elastica\Index;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;

final class AsyncObjectPersisterTest extends AppKernelTestCase
{
    /**
     * The two async consumers can update the same document at once (BY-NIGHTFR-4VD).
     */
    public function testAConflictingUpdateIsRetried(): void
    {
        $client = self::getContainer()->get('fos_elastica.client.default');
        self::assertInstanceOf(Client::class, $client);

        $bulk = new Bulk($client)->addDocument(new Document('1', ['name' => 'Concert']), Action::OP_TYPE_UPDATE);

        self::assertSame(3, $bulk->getActions()[0]->getMetadata()['retry_on_conflict'] ?? null);
    }

    public function testAnUpdatedEventIsUpserted(): void
    {
        $event = EventFactory::createOne();
        $decorated = new class implements ObjectPersisterInterface {
            /** @var list<string> */
            public array $calls = [];

            public function handlesObject(object $object): bool
            {
                return true;
            }

            public function insertOne(object $object): void
            {
                $this->calls[] = 'insertOne';
            }

            public function replaceOne(object $object): void
            {
                $this->calls[] = 'replaceOne';
            }

            public function deleteOne(object $object): void
            {
                $this->calls[] = 'deleteOne';
            }

            public function deleteById(string $id, string|bool $routing = false): void
            {
                $this->calls[] = 'deleteById';
            }

            public function insertMany(array $objects): void
            {
                $this->calls[] = 'insertMany';
            }

            public function replaceMany(array $objects): void
            {
                $this->calls[] = 'replaceMany';
            }

            public function deleteMany(array $objects): void
            {
                $this->calls[] = 'deleteMany';
            }

            public function deleteManyByIdentifiers(array $identifiers, string|bool $routing = false): void
            {
                $this->calls[] = 'deleteManyByIdentifiers';
            }
        };

        $persister = new AsyncObjectPersister(
            $decorated,
            new Index(new Client(), 'event'),
            Event::class,
            new ElasticaMode(),
            self::getContainer()->get(TransactionalMessageDispatcher::class),
            self::getContainer()->get(EntityManagerInterface::class),
        );
        $persister->doReplaceMany([$event]);

        // An upsert, so a document missing from the index is created rather than rejected.
        // The fields that became null are not left behind: the index serializes them as null
        // (serialize_null in fos_elastica.yaml), and the merge clears what was stored.
        self::assertSame(['replaceMany'], $decorated->calls);
    }
}
