<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Elasticsearch;

use App\Elasticsearch\Handler\RefreshEventDocumentsHandler;
use App\Elasticsearch\Message\RefreshEventDocuments;
use App\Elasticsearch\Message\ReplaceManyDocuments;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Tests\AppKernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class RefreshEventDocumentsHandlerTest extends AppKernelTestCase
{
    public function testTheIndexedEventsOfAPlaceAreReindexed(): void
    {
        $place = PlaceFactory::createOne();
        $indexed = EventFactory::createOne(['place' => $place]);
        EventFactory::createOne(['place' => $place, 'draft' => true]);
        EventFactory::createOne(['place' => $place, 'duplicateOf' => $indexed]);
        EventFactory::createOne();

        self::assertSame([$indexed->getId()], $this->reindexedIds(new RefreshEventDocuments(placeIds: [(int) $place->getId()])));
    }

    public function testTheEventsOfATagAsCategoryOrThemeAreReindexed(): void
    {
        $tag = TagFactory::createOne();
        $asCategory = EventFactory::createOne(['category' => $tag]);
        $asTheme = EventFactory::createOne(['themes' => [$tag]]);
        EventFactory::createOne();

        $ids = $this->reindexedIds(new RefreshEventDocuments(tagIds: [(int) $tag->getId()]));
        sort($ids);

        self::assertSame([$asCategory->getId(), $asTheme->getId()], $ids);
    }

    public function testAnEventOutOfTheIndexIsLeftOut(): void
    {
        $indexed = EventFactory::createOne();
        $draft = EventFactory::createOne(['draft' => true]);

        self::assertSame([$indexed->getId()], $this->reindexedIds(new RefreshEventDocuments(eventIds: [(int) $indexed->getId(), (int) $draft->getId()])));
    }

    /**
     * @return list<int|string>
     */
    private function reindexedIds(RefreshEventDocuments $message): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $transport->reset();

        self::getContainer()->get(RefreshEventDocumentsHandler::class)($message);

        $ids = [];
        foreach ($transport->getSent() as $envelope) {
            $sent = $envelope->getMessage();
            self::assertInstanceOf(ReplaceManyDocuments::class, $sent);
            array_push($ids, ...$sent->getEntityIds());
        }

        return $ids;
    }
}
