<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;

/**
 * @extends ServiceEntityRepository<Page>
 *
 * @method Page|null find($id, $lockMode = null, $lockVersion = null)
 * @method Page|null findOneBy(array $criteria, array $orderBy = null)
 * @method Page[]    findAll()
 * @method Page[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    /**
     * @return iterable<array>
     */
    public function findAllSitemap(): iterable
    {
        return $this
            ->createQueryBuilder('p')
            ->select('p.slug, p.updatedAt')
            ->getQuery()
            ->toIterable();
    }

    /**
     * The editorial pages /llms.txt lists, by title, read by pages of 500 with a cursor on (title, id).
     *
     * @return iterable<array{id: int, slug: string, title: string, metaDescription: string|null}>
     */
    public function findAllLlmsTxt(): iterable
    {
        /** @var CursorPagination<array{id: int, slug: string, title: string, metaDescription: string|null}> $pagination */
        $pagination = new CursorPagination(
            $this->createQueryBuilder('p')->select('p.id, p.slug, p.title, p.metaDescription'),
            new OrderConfigurations(
                new OrderConfiguration('p.title', static fn (array $page): string => (string) $page['title'], true, false),
                new OrderConfiguration('p.id', static fn (array $page): int => (int) $page['id'], true, true),
            ),
            500,
            // scalar rows: no collection to fetch-join, and no root entity for the paginator's id subquery
            fetchJoinCollection: false,
        );

        return $pagination->getResults();
    }
}
