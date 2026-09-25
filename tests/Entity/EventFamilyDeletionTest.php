<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Entity;

use App\Factory\EventFactory;
use App\Factory\ParserDataFactory;
use App\Reject\Reject;
use App\Tests\AppKernelTestCase;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

/**
 * Deleting an event deletes the rows redirecting to it (Event::$duplicates).
 */
final class EventFamilyDeletionTest extends AppKernelTestCase
{
    public function testDeletingAnEventDeletesTheRowsRedirectingToIt(): void
    {
        $canonical = EventFactory::createOne(['externalId' => 'show-1', 'externalOrigin' => 'awin.fnac']);
        EventFactory::createOne(['externalId' => 'show-2', 'externalOrigin' => 'awin.fnac', 'duplicateOf' => $canonical]);
        EventFactory::createOne(['externalId' => 'show-3', 'externalOrigin' => 'awin.fnac', 'duplicateOf' => $canonical]);
        $unrelated = EventFactory::createOne(['externalId' => 'other', 'externalOrigin' => 'awin.fnac']);
        refresh($canonical);
        self::assertCount(2, $canonical->getDuplicates());

        delete($canonical);

        self::assertSame(0, EventFactory::count(['externalId' => ['show-1', 'show-2', 'show-3']]));
        self::assertSame(1, EventFactory::count(['id' => $unrelated->getId()]));
        // Recorded as deleted, so the next import does not bring them back
        foreach (['show-1', 'show-2', 'show-3'] as $externalId) {
            self::assertSame(Reject::EVENT_DELETED, ParserDataFactory::find(['externalId' => $externalId, 'externalOrigin' => 'awin.fnac'])->getReason());
        }
    }

    public function testDeletingADuplicateLeavesItsFamily(): void
    {
        $canonical = EventFactory::createOne();
        $duplicate = EventFactory::createOne(['duplicateOf' => $canonical]);
        $sibling = EventFactory::createOne(['duplicateOf' => $canonical]);

        delete($duplicate);

        self::assertSame(1, EventFactory::count(['id' => $canonical->getId()]));
        self::assertSame(1, EventFactory::count(['id' => $sibling->getId()]));
    }

    public function testRowsRedirectingToADuplicateGoToo(): void
    {
        $canonical = EventFactory::createOne();
        $duplicate = EventFactory::createOne(['duplicateOf' => $canonical]);
        $chained = EventFactory::createOne(['duplicateOf' => $duplicate]);

        delete($canonical);

        self::assertSame(0, EventFactory::count(['id' => [$canonical->getId(), $duplicate->getId(), $chained->getId()]]));
    }

    public function testRowsRedirectingToEachOtherAreDeletedOnce(): void
    {
        $first = EventFactory::createOne();
        $second = EventFactory::createOne(['duplicateOf' => $first]);
        $first->setDuplicateOf($second);
        save($first);

        delete($first);

        self::assertSame(0, EventFactory::count(['id' => [$first->getId(), $second->getId()]]));
    }
}
