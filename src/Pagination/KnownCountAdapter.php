<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Pagination;

use Pagerfanta\Adapter\AdapterInterface;

/**
 * Pages whose number of results is already stored (a counter column): no COUNT query, and no query at all for a page
 * past the last one, the only page of nothing included.
 *
 * @template T
 *
 * @implements AdapterInterface<T>
 */
final readonly class KnownCountAdapter implements AdapterInterface
{
    /**
     * @param AdapterInterface<T> $adapter
     * @param int<0, max>         $nbResults
     */
    public function __construct(
        private AdapterInterface $adapter,
        private int $nbResults,
    ) {
    }

    public function getNbResults(): int
    {
        return $this->nbResults;
    }

    public function getSlice(int $offset, int $length): iterable
    {
        if ($offset >= $this->nbResults) {
            return [];
        }

        return $this->adapter->getSlice($offset, $length);
    }
}
