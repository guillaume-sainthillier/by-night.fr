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
use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * Paginator for transformed/mapped items that preserves the original pagination metadata.
 *
 * Use this when you need to transform entities from a paginated query into DTOs
 * while keeping the pagination information from the original result set.
 *
 * @template T of object
 *
 * @implements PaginatorInterface<T>
 */
final class TransformedPaginator implements IteratorAggregate, PaginatorInterface
{
    /**
     * @param list<T> $items        The transformed items for the current page
     * @param int     $totalItems   Total number of items across all pages
     * @param int     $currentPage  The current page number (1-indexed)
     * @param int     $itemsPerPage Number of items per page
     */
    public function __construct(
        private readonly array $items,
        private readonly int $totalItems,
        private readonly int $currentPage,
        private readonly int $itemsPerPage,
    ) {
    }

    public function count(): int
    {
        return \count($this->items);
    }

    public function getLastPage(): float
    {
        if (0 >= $this->itemsPerPage) {
            return 1.;
        }

        return ceil($this->totalItems / $this->itemsPerPage) ?: 1.;
    }

    public function getTotalItems(): float
    {
        return (float) $this->totalItems;
    }

    public function getCurrentPage(): float
    {
        return (float) $this->currentPage;
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->itemsPerPage;
    }

    /**
     * @return Traversable<T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
