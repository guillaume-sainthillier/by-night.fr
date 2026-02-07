<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Pagination;

use App\Contracts\MultipleEagerLoaderInterface;
use Pagerfanta\Adapter\AdapterInterface;
use Traversable;

/**
 * @template T of object
 *
 * @implements AdapterInterface<T>
 */
final readonly class MultipleEagerLoadingAdapter implements AdapterInterface
{
    /**
     * @param AdapterInterface<T>             $adapter
     * @param MultipleEagerLoaderInterface<T> $multipleEagerLoader
     */
    public function __construct(
        private AdapterInterface $adapter,
        private MultipleEagerLoaderInterface $multipleEagerLoader,
        private array $options = [],
    ) {
    }

    public function getNbResults(): int
    {
        return $this->adapter->getNbResults();
    }

    public function getSlice(int $offset, int $length): iterable
    {
        $results = $this->adapter->getSlice($offset, $length);
        $results = $results instanceof Traversable ? iterator_to_array($results) : $results;

        if ([] !== $results) {
            $this->multipleEagerLoader->loadAllEager($results, $this->options);
        }

        return $results;
    }
}
