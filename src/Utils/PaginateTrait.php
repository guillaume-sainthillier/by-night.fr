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
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;

trait PaginateTrait
{
    protected function createQueryBuilderPaginator(QueryBuilder $queryBuilder, int $page, int $limit): PagerfantaInterface
    {
        $adapter = new QueryAdapter($queryBuilder);

        return $this->createPaginatorFromAdapter($adapter, $page, $limit);
    }

    /**
     * @template TPaginateObject of object
     *
     * @param MultipleEagerLoaderInterface<TPaginateObject> $multipleEagerLoader
     *
     * @return PagerfantaInterface<TPaginateObject>
     */
    protected function createMultipleEagerLoadingPaginator(
        QueryBuilder $query,
        MultipleEagerLoaderInterface $multipleEagerLoader,
        int $page,
        int $limit,
        array $options = [],
    ): PagerfantaInterface {
        $adapter = new QueryAdapter($query);

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

    protected function createEmptyPaginator(int $page, int $limit): PagerfantaInterface
    {
        $adapter = new ArrayAdapter([]);

        return $this->createPaginatorFromAdapter($adapter, $page, $limit);
    }

    protected function createPaginatorFromAdapter(AdapterInterface $adapter, int $page, int $limit): PagerfantaInterface
    {
        $pagerfanta = new Pagerfanta($adapter);

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
