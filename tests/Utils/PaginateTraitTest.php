<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\UserEvent;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;
use App\Utils\PaginateTrait;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use LogicException;
use Pagerfanta\PagerfantaInterface;

final class PaginateTraitTest extends AppKernelTestCase
{
    public function testWithoutFetchJoinCollectionRunsThePlainQueryAndASimpleCount(): void
    {
        $place = PlaceFactory::createOne();
        EventFactory::createMany(5, ['place' => $place]);
        EventFactory::createOne();

        $queryBuilder = $this->entityManager()
            ->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->join('e.place', 'p')
            ->where('p = :place')
            ->setParameter('place', $place)
            ->orderBy('e.name');

        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        self::assertInstanceOf(BacktraceDebugDataHolder::class, $queries);
        $queries->reset();

        $pager = $this->paginate($queryBuilder, 2, false);

        self::assertCount(2, iterator_to_array($pager->getCurrentPageResults()));
        self::assertSame(5, $pager->getNbResults());
        self::assertSame(3, $pager->getNbPages());

        $sqls = array_column($queries->getData()['default'] ?? [], 'sql');
        self::assertCount(2, $sqls);
        foreach ($sqls as $sql) {
            // No derived table of the paginator's output walkers
            self::assertStringNotContainsString('dctrn_', $sql);
        }
        // The query itself, limited to the page, then COUNT(DISTINCT id) without ORDER BY (CountWalker)
        self::assertMatchesRegularExpression('/^SELECT e0_\.id AS id_0,.* ORDER BY e0_\.name ASC LIMIT 2$/', $sqls[0]);
        self::assertMatchesRegularExpression('/^SELECT count\(DISTINCT e0_\.id\) AS \w+ FROM \W?event\W? e0_ INNER JOIN place p1_ .* WHERE p1_\.id = \?$/i', $sqls[1]);
    }

    public function testIdsFirstSortsTheIdsAloneThenLoadsTheirRowsInOrder(): void
    {
        $place = PlaceFactory::createOne();
        foreach (['Delta', 'Alpha', 'Echo', 'Charlie', 'Bravo'] as $name) {
            EventFactory::createOne(['place' => $place, 'name' => $name]);
        }
        EventFactory::createOne(['name' => 'Ailleurs']);

        $queryBuilder = $this->entityManager()
            ->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->join('e.place', 'p')
            ->where('p = :place')
            ->setParameter('place', $place)
            ->orderBy('e.name');

        $queries = $this->queries();
        $queries->reset();

        $pager = $this->paginator()->createIdsFirstPaginator($queryBuilder, self::getContainer()->get(EventRepository::class), 1, 3);

        self::assertSame(['Alpha', 'Bravo', 'Charlie'], array_map(static fn (Event $event): ?string => $event->getName(), iterator_to_array($pager->getCurrentPageResults())));
        self::assertSame(5, $pager->getNbResults());

        $sqls = array_column($queries->getData()['default'] ?? [], 'sql');
        // The ids alone are sorted, then the rows of the page are read by id
        self::assertMatchesRegularExpression('/^SELECT e0_\.id AS id_0 FROM \W?event\W? e0_ INNER JOIN place p1_ .* ORDER BY e0_\.name ASC LIMIT 3$/', $sqls[0]);
        self::assertMatchesRegularExpression('/^SELECT e0_\.id AS id_0,.* WHERE e0_\.id IN \(/', $sqls[1]);
    }

    public function testAStoredCountOfZeroRunsNoQuery(): void
    {
        $event = EventFactory::createOne();
        $queryBuilder = self::getContainer()->get(CommentRepository::class)->findAllByEventQueryBuilder($event);

        $queries = $this->queries();
        $queries->reset();

        $pager = $this->paginator()->createMultipleEagerLoadingPaginator($queryBuilder, self::getContainer()->get(CommentRepository::class), 1, 10, nbResults: 0);

        self::assertSame([], iterator_to_array($pager->getCurrentPageResults()));
        self::assertSame(0, $pager->getNbResults());
        self::assertSame([], $queries->getData()['default'] ?? []);
    }

    public function testRefusesAToManyJoinWithoutFetchJoinCollection(): void
    {
        $queryBuilder = $this->entityManager()
            ->createQueryBuilder()
            ->select('c')
            ->from(Comment::class, 'c')
            ->join('c.event', 'e')
            ->leftJoin('c.children', 'children');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"c.children"');

        $this->paginate($queryBuilder, 10, false);
    }

    public function testRefusesAnArbitraryEntityJoinWithoutFetchJoinCollection(): void
    {
        $queryBuilder = $this->entityManager()
            ->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->join(UserEvent::class, 'ue', 'WITH', 'ue.event = e');

        $this->expectException(LogicException::class);

        $this->paginate($queryBuilder, 10, false);
    }

    public function testKeepsAToManyJoinWithFetchJoinCollection(): void
    {
        $queryBuilder = $this->entityManager()
            ->createQueryBuilder()
            ->select('c')
            ->from(Comment::class, 'c')
            ->leftJoin('c.children', 'children');

        self::assertSame(0, $this->paginate($queryBuilder, 10, true)->getNbResults());
    }

    /**
     * @return PagerfantaInterface<mixed>
     */
    private function paginate(QueryBuilder $queryBuilder, int $limit, bool $fetchJoinCollection): PagerfantaInterface
    {
        $paginator = new class {
            use PaginateTrait {
                createQueryBuilderPaginator as public;
            }
        };

        return $paginator->createQueryBuilderPaginator($queryBuilder, 1, $limit, $fetchJoinCollection);
    }

    private function paginator(): object
    {
        return new class {
            use PaginateTrait {
                createIdsFirstPaginator as public;
                createMultipleEagerLoadingPaginator as public;
            }
        };
    }

    private function queries(): BacktraceDebugDataHolder
    {
        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        self::assertInstanceOf(BacktraceDebugDataHolder::class, $queries);

        return $queries;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
