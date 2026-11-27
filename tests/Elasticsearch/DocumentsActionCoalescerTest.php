<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Elasticsearch;

use App\Elasticsearch\DocumentsActionCoalescer;
use App\Elasticsearch\Message\DeleteManyDocumentsByIdentifiers;
use App\Elasticsearch\Message\InsertManyDocuments;
use App\Elasticsearch\Message\ReplaceManyDocuments;
use App\Entity\Event;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use stdClass;

final class DocumentsActionCoalescerTest extends TestCase
{
    public function testTheFlushesOfABatchMakeOneMessagePerAction(): void
    {
        $messages = DocumentsActionCoalescer::coalesce([
            new InsertManyDocuments('event', Event::class, [1, 2]),
            new ReplaceManyDocuments('event', Event::class, [10, 11]),
            new InsertManyDocuments('event', Event::class, [3]),
            new ReplaceManyDocuments('event', Event::class, [11, 12]),
            new DeleteManyDocumentsByIdentifiers('event', [20], false),
            new DeleteManyDocumentsByIdentifiers('event', [21], false),
        ]);

        self::assertEquals([
            new InsertManyDocuments('event', Event::class, [1, 2, 3]),
            new ReplaceManyDocuments('event', Event::class, [10, 11, 12]),
            new DeleteManyDocumentsByIdentifiers('event', [20, 21], false),
        ], $messages);
    }

    public function testTheLastActionOnADocumentWins(): void
    {
        $messages = DocumentsActionCoalescer::coalesce([
            new ReplaceManyDocuments('event', Event::class, [1, 2]),
            // 1 became a draft, then was published again; 2 became a draft
            new DeleteManyDocumentsByIdentifiers('event', [1, 2], false),
            new ReplaceManyDocuments('event', Event::class, [1]),
        ]);

        self::assertEquals([
            new ReplaceManyDocuments('event', Event::class, [1]),
            new DeleteManyDocumentsByIdentifiers('event', [2], false),
        ], $messages);
    }

    public function testAnInsertedDocumentReplacedNextIsInserted(): void
    {
        $messages = DocumentsActionCoalescer::coalesce([
            new InsertManyDocuments('event', Event::class, [1]),
            new ReplaceManyDocuments('event', Event::class, [1, 2]),
        ]);

        self::assertEquals([
            new InsertManyDocuments('event', Event::class, [1]),
            new ReplaceManyDocuments('event', Event::class, [2]),
        ], $messages);
    }

    public function testTheOtherMessagesKeepTheirPlace(): void
    {
        $first = new stdClass();
        $last = new stdClass();

        $messages = DocumentsActionCoalescer::coalesce([
            $first,
            new ReplaceManyDocuments('event', Event::class, [1]),
            new ReplaceManyDocuments('user', User::class, [5]),
            $last,
            new ReplaceManyDocuments('event', Event::class, [2]),
        ]);

        self::assertEquals([
            $first,
            new ReplaceManyDocuments('event', Event::class, [1, 2]),
            new ReplaceManyDocuments('user', User::class, [5]),
            $last,
        ], $messages);
        self::assertSame($first, $messages[0]);
        self::assertSame($last, $messages[3]);
    }
}
