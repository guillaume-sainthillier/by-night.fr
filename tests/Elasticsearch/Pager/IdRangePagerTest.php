<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Elasticsearch\Pager;

use App\Elasticsearch\Pager\IdRangePager;
use App\Entity\Event;
use App\Factory\EventFactory;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;

final class IdRangePagerTest extends AppKernelTestCase
{
    private EventRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = self::getContainer()->get(EventRepository::class);
    }

    public function testEachPageHoldsTheIndexableEventsOfABlockOfIdsNewestFirst(): void
    {
        $ids = [];
        foreach (range(1, 5) as $i) {
            $ids[] = EventFactory::createOne()->getId();
        }
        // The top block: neither is indexed
        EventFactory::createOne(['draft' => true]);
        EventFactory::createOne(['duplicateOf' => EventFactory::find($ids[0])]);

        $pager = $this->createPager(perPage: 2);

        self::assertSame([
            [],
            [$ids[4], $ids[3]],
            [$ids[2], $ids[1]],
            [$ids[0]],
        ], array_map(fn (int $page): array => $this->getPageIds($pager, $page), [1, 2, 3, 4]));
    }

    public function testThePagesKeepTheirBlockWhenEventsAreCreatedMeanwhile(): void
    {
        $older = EventFactory::createOne()->getId();
        $newer = EventFactory::createOne()->getId();
        $pager = $this->createPager(perPage: 1);

        // Created while the workers handle the pages: the Doctrine listeners index it
        EventFactory::createOne();

        self::assertSame([[$newer], [$older]], [$this->getPageIds($pager, 1), $this->getPageIds($pager, 2)]);
    }

    public function testThereIsAPagePerBlockOfIdsUpToTheCeiling(): void
    {
        $pager = new IdRangePager($this->repository->createIsActiveQueryBuilder(), 12);
        $pager->setMaxPerPage(5);
        self::assertSame(3, $pager->getNbPages());

        $emptyTable = new IdRangePager($this->repository->createIsActiveQueryBuilder(), 0);
        self::assertSame(1, $emptyTable->getNbPages());
    }

    public function testTheResultsAreTheIndexableEvents(): void
    {
        EventFactory::createMany(3);
        EventFactory::createOne(['draft' => true]);

        self::assertSame(3, $this->createPager(perPage: 2)->getNbResults());
    }

    private function createPager(int $perPage): IdRangePager
    {
        $pager = new IdRangePager($this->repository->createIsActiveQueryBuilder(), $this->repository->findMaxId());
        $pager->setMaxPerPage($perPage);

        return $pager;
    }

    /**
     * @return array<int, int|null>
     */
    private function getPageIds(IdRangePager $pager, int $page): array
    {
        $pager->setCurrentPage($page);

        return array_map(static fn (Event $event): ?int => $event->getId(), $pager->getCurrentPageResults());
    }
}
