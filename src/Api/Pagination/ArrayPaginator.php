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
 * Simple paginator implementation for pre-computed arrays of items.
 *
 * Use this when results are already computed and can't be wrapped in a Pagerfanta,
 * for example when combining results from multiple sources.
 *
 * @template T of object
 *
 * @implements PaginatorInterface<T>
 */
final class ArrayPaginator implements IteratorAggregate, PaginatorInterface
{
    /**
     * @param list<T> $items
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

        return max(ceil($this->totalItems / $this->itemsPerPage), 1.);
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
