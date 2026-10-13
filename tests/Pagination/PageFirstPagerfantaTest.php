<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Pagination;

use App\Entity\Page;
use App\Factory\PageFactory;
use App\Pagination\PageFirstPagerfanta;
use App\SearchRepository\ResultWindow;
use App\Tests\AppKernelTestCase;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;

final class PageFirstPagerfantaTest extends AppKernelTestCase
{
    /**
     * Pagers ask hasNextPage() before rendering their page. The Doctrine ORM adapter answers a
     * count asked before any slice with a one-row probe (ids, rows and COUNT), then runs the
     * three queries again for the page: fetching the page first leaves one of each.
     */
    public function testCountingBeforeReadingThePageRunsThePageQueriesOnce(): void
    {
        PageFactory::createMany(3);

        $queryBuilder = self::getContainer()->get(EntityManagerInterface::class)
            ->createQueryBuilder()
            ->select('p')
            ->from(Page::class, 'p')
            ->orderBy('p.id');
        $pager = new PageFirstPagerfanta(new QueryAdapter($queryBuilder));
        $pager->setMaxPerPage(2);

        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        self::assertInstanceOf(BacktraceDebugDataHolder::class, $queries);
        $queries->reset();

        self::assertTrue($pager->hasNextPage());
        self::assertSame(3, $pager->getNbResults());
        self::assertCount(2, iterator_to_array($pager->getCurrentPageResults()));
        self::assertCount(3, $queries->getData()['default'] ?? []);
    }

    /**
     * BY-NIGHTFR-6CK: "/grenoble/2021" fetched hits 30,300 and up before the agenda's cap
     * could send it to its last page, and Elasticsearch refused it past its 10,000 hits.
     */
    public function testAPagePastTheCapIsCountedWithoutBeingFetched(): void
    {
        $adapter = new class implements AdapterInterface {
            /** @var list<int> */
            public array $offsets = [];

            public function getNbResults(): int
            {
                return 40_000;
            }

            public function getSlice(int $offset, int $length): iterable
            {
                $this->offsets[] = $offset;

                return [];
            }
        };
        $pager = new PageFirstPagerfanta($adapter);
        $pager->setAllowOutOfRangePages(true)->setMaxPerPage(15)->setCurrentPage(2021);
        $pager->setMaxNbPages(ResultWindow::getMaxPages(15));

        self::assertSame(666, $pager->getNbPages());
        self::assertSame([], $adapter->offsets);

        $pager->setCurrentPage(666);
        self::assertSame(666, $pager->getNbPages());
        self::assertSame([9975], $adapter->offsets);
    }
}
