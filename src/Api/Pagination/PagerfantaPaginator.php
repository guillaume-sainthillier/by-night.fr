<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Pagination;

use ApiPlatform\State\Pagination\PaginatorInterface;
use IteratorAggregate;
use Pagerfanta\PagerfantaInterface;
use Traversable;

/**
 * Paginator adapter that wraps a PagerfantaInterface to implement API Platform's PaginatorInterface.
 *
 * @template T of object
 *
 * @implements PaginatorInterface<T>
 */
final class PagerfantaPaginator implements IteratorAggregate, PaginatorInterface
{
    /**
     * @param PagerfantaInterface<T> $pagerfanta
     */
    public function __construct(
        private readonly PagerfantaInterface $pagerfanta,
    ) {
    }

    public function count(): int
    {
        return \count($this->pagerfanta);
    }

    public function getLastPage(): float
    {
        return (float) $this->pagerfanta->getNbPages();
    }

    public function getTotalItems(): float
    {
        return (float) $this->pagerfanta->getNbResults();
    }

    public function getCurrentPage(): float
    {
        return (float) $this->pagerfanta->getCurrentPage();
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->pagerfanta->getMaxPerPage();
    }

    /**
     * @return Traversable<T>
     */
    public function getIterator(): Traversable
    {
        return $this->pagerfanta->getIterator();
    }
}
