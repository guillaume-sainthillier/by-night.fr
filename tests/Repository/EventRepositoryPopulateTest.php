<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Repository;

use App\Entity\Event;
use App\Factory\EventFactory;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

final class EventRepositoryPopulateTest extends AppKernelTestCase
{
    public function testThePopulatePagesAreStableWhenEventsShareTheirCreationTime(): void
    {
        // An import batch: every event created in the same second
        $createdAt = new DateTimeImmutable('2026-09-22 06:15:00');
        $ids = [];
        foreach (range(1, 6) as $i) {
            $ids[] = EventFactory::createOne(['createdAt' => $createdAt])->getId();
        }

        $repository = self::getContainer()->get(EventRepository::class);
        $pages = [];
        foreach ([0, 2, 4] as $offset) {
            $pages[] = array_map(
                static fn (Event $event): ?int => $event->getId(),
                $repository->createIsActiveQueryBuilder()->setFirstResult($offset)->setMaxResults(2)->getQuery()->getResult()
            );
        }

        $paged = array_merge(...$pages);
        rsort($ids);
        self::assertSame($ids, $paged, 'Each event on exactly one page, newest first');
    }
}
