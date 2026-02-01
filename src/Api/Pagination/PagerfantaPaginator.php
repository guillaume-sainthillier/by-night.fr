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
use Closure;
use IteratorAggregate;
use Pagerfanta\PagerfantaInterface;
use Traversable;

/**
 * Paginator adapter that wraps a PagerfantaInterface to implement API Platform's PaginatorInterface.
 *
 * Supports an optional transformation callback to map items to DTOs.
 *
 * @template TInput
 * @template TOutput of object
 *
 * @implements PaginatorInterface<TOutput>
 */
final class PagerfantaPaginator implements IteratorAggregate, PaginatorInterface
{
    /**
     * @param PagerfantaInterface<TInput>     $pagerfanta
     * @param (Closure(TInput): TOutput)|null $transformer Optional callback to transform each item
     */
    public function __construct(
        private readonly PagerfantaInterface $pagerfanta,
        private readonly ?Closure $transformer = null,
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
     * @return Traversable<TOutput>
     */
    public function getIterator(): Traversable
    {
        if (null === $this->transformer) {
            /* @var Traversable<TOutput> */
            return $this->pagerfanta->getIterator();
        }

        foreach ($this->pagerfanta as $item) {
            yield ($this->transformer)($item);
        }
    }
}
