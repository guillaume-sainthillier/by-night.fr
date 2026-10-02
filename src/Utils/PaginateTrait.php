<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use App\Contracts\MultipleEagerLoaderInterface;
use App\Pagination\MultipleEagerLoadingAdapter;
use App\Pagination\PageFirstPagerfanta;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use LogicException;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\PagerfantaInterface;

trait PaginateTrait
{
    /**
     * @param bool $fetchJoinCollection see createQueryAdapter(): pass false only when the query builder joins no to-many association
     */
    protected function createQueryBuilderPaginator(QueryBuilder $queryBuilder, int $page, int $limit, bool $fetchJoinCollection = true): PagerfantaInterface
    {
        $adapter = $this->createQueryAdapter($queryBuilder, $fetchJoinCollection);

        return $this->createPaginatorFromAdapter($adapter, $page, $limit);
    }

    /**
     * @template TPaginateObject of object
     *
     * @param MultipleEagerLoaderInterface<TPaginateObject> $multipleEagerLoader
     * @param bool                                          $fetchJoinCollection see createQueryAdapter(): pass false only when the query builder joins no to-many association
     *
     * @return PagerfantaInterface<TPaginateObject>
     */
    protected function createMultipleEagerLoadingPaginator(
        QueryBuilder $query,
        MultipleEagerLoaderInterface $multipleEagerLoader,
        int $page,
        int $limit,
        array $options = [],
        bool $fetchJoinCollection = true,
    ): PagerfantaInterface {
        $adapter = $this->createQueryAdapter($query, $fetchJoinCollection);

        return $this->createMultipleEagerLoadingPaginatorFromAdapter($adapter, $multipleEagerLoader, $page, $limit, $options);
    }

    /**
     * @template TPaginateObject of object
     *
     * @param MultipleEagerLoaderInterface<TPaginateObject> $multipleEagerLoader
     *
     * @return PagerfantaInterface<TPaginateObject>
     */
    protected function createMultipleEagerLoadingPaginatorFromAdapter(
        AdapterInterface $adapter,
        MultipleEagerLoaderInterface $multipleEagerLoader,
        int $page,
        int $limit,
        array $options = [],
    ): PagerfantaInterface {
        $adapter = new MultipleEagerLoadingAdapter($adapter, $multipleEagerLoader, $options);

        return $this->createPaginatorFromAdapter($adapter, $page, $limit);
    }

    /**
     * By default Doctrine's paginator assumes the query fetch-joins a to-many association, which would
     * repeat its root rows: it reads the page's ids with SELECT DISTINCT over a derived table of every
     * selected column, then the rows of those ids, and counts over another derived table of the same rows.
     * On wide tables (event and its TEXT columns), MySQL sorts those derived tables on disk.
     *
     * Without a to-many join each root has a single row, so false runs the query itself with its LIMIT and
     * counts with COUNT(DISTINCT <id>) (CountWalker, no output walker). It must stay true as soon as the query
     * joins a to-many association, even only to filter on it: its repeated rows would shorten the pages and
     * inflate the count. Collections are loaded afterwards by the MultipleEagerLoaderInterface instead.
     */
    private function createQueryAdapter(QueryBuilder $queryBuilder, bool $fetchJoinCollection): QueryAdapter
    {
        if ($fetchJoinCollection) {
            return new QueryAdapter($queryBuilder);
        }

        $this->assertNoToManyJoin($queryBuilder);

        return new QueryAdapter($queryBuilder, fetchJoinCollection: false, useOutputWalkers: false);
    }

    /**
     * The repository methods that build these query builders live far from the paginators: a to-many join added
     * there later must fail loudly rather than silently shorten the pages.
     */
    private function assertNoToManyJoin(QueryBuilder $queryBuilder): void
    {
        $entityManager = $queryBuilder->getEntityManager();
        $classes = array_combine($queryBuilder->getRootAliases(), $queryBuilder->getRootEntities());

        /** @var array<string, list<Join>> $joins */
        $joins = $queryBuilder->getDQLPart('join');
        foreach ($joins as $rootJoins) {
            foreach ($rootJoins as $join) {
                // "alias.association"; anything else is an arbitrary entity join, which may match several rows
                $path = explode('.', $join->getJoin(), 2);
                $class = $classes[$path[0]] ?? null;
                $metadata = null !== $class && isset($path[1]) ? $entityManager->getClassMetadata($class) : null;
                if (null === $metadata || !$metadata->hasAssociation($path[1]) || $metadata->isCollectionValuedAssociation($path[1])) {
                    throw new LogicException(\sprintf('The join "%s" may repeat the root rows: paginate this query builder with $fetchJoinCollection = true.', $join->getJoin()));
                }

                $classes[(string) $join->getAlias()] = $metadata->getAssociationTargetClass($path[1]);
            }
        }
    }

    protected function createEmptyPaginator(int $page, int $limit): PagerfantaInterface
    {
        $adapter = new ArrayAdapter([]);

        return $this->createPaginatorFromAdapter($adapter, $page, $limit);
    }

    protected function createPaginatorFromAdapter(AdapterInterface $adapter, int $page, int $limit): PagerfantaInterface
    {
        $pagerfanta = new PageFirstPagerfanta($adapter);

        $this->updatePaginator($pagerfanta, $page, $limit);

        return $pagerfanta;
    }

    protected function updatePaginator(PagerfantaInterface $pagerfanta, int $page, int $limit): void
    {
        $pagerfanta
            ->setAllowOutOfRangePages(true)
            ->setMaxPerPage($limit)
            ->setCurrentPage($page);
    }
}
