<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Contracts\ComparatorInterface;

class ComparatorHandler
{
    /** @var ComparatorInterface[] */
    private $comparators;

    public function __construct(iterable $comparators)
    {
        $this->comparators = $comparators;
    }

    public function getComparator(object $dto): ComparatorInterface
    {
        foreach ($this->comparators as $comparator) {
            if ($comparator->supports($dto)) {
                return $comparator;
            }
        }

        throw new \RuntimeException(sprintf('Unable to get comparator for class "%s"', \get_class($dto)));
    }
}
