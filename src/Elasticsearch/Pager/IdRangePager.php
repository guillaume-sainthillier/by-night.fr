<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Pager;

use Doctrine\ORM\QueryBuilder;
use FOS\ElasticaBundle\Provider\PagerInterface;

/**
 * Pages through a query by blocks of ids counted down from a fixed ceiling: page 1 holds the
 * rows whose id is in ]ceiling - maxPerPage, ceiling], page 2 the block below, and so on.
 *
 * Every page is a primary key range scan wherever it sits: no OFFSET walking through the pages
 * before it, no COUNT to know how many pages there are, so the last page costs what the first
 * does. A page holds at most maxPerPage rows: fewer where rows were deleted or filtered out,
 * none in an empty block. It is addressed by its number alone, so the elastica workers can
 * handle the pages in any order, which a cursor (the last id of the previous page) would not allow.
 *
 * Rows created after the ceiling are left out: the Doctrine listeners index them as they come.
 */
final class IdRangePager implements PagerInterface
{
    private int $currentPage = 1;

    private int $maxPerPage = 100;

    private ?int $nbResults = null;

    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly int $ceiling,
    ) {
    }

    public function getNbResults(): int
    {
        // Only asked by the progress bar of a synchronous populate
        return $this->nbResults ??= (int) (clone $this->queryBuilder)
            ->select(\sprintf('COUNT(%s.id)', $this->getAlias()))
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getNbPages(): int
    {
        return max(1, (int) ceil($this->ceiling / $this->maxPerPage));
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function setCurrentPage(int $page): void
    {
        $this->currentPage = $page;
    }

    public function getMaxPerPage(): int
    {
        return $this->maxPerPage;
    }

    public function setMaxPerPage(int $perPage): void
    {
        $this->maxPerPage = $perPage;
    }

    /**
     * Not kept by the pager: a page of hydrated entities only lives as long as its insertion.
     *
     * @return array<int, object>
     */
    public function getCurrentPageResults(): array
    {
        $alias = $this->getAlias();
        $upper = $this->ceiling - ($this->currentPage - 1) * $this->maxPerPage;

        return (clone $this->queryBuilder)
            ->andWhere(\sprintf('%1$s.id > :idRangeLower AND %1$s.id <= :idRangeUpper', $alias))
            ->setParameter('idRangeLower', $upper - $this->maxPerPage)
            ->setParameter('idRangeUpper', $upper)
            ->orderBy(\sprintf('%s.id', $alias), 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function getAlias(): string
    {
        return $this->queryBuilder->getRootAliases()[0];
    }
}
