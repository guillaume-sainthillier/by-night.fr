<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Contracts\ComparatorInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ComparatorHandler
{
    /**
     * @param ComparatorInterface[] $comparators
     */
    public function __construct(
        #[AutowireIterator(ComparatorInterface::class)]
        private iterable $comparators,
    ) {
    }

    public function getComparator(object $dto): ComparatorInterface
    {
        foreach ($this->comparators as $comparator) {
            if ($comparator->supports($dto)) {
                return $comparator;
            }
        }

        throw new RuntimeException(\sprintf('Unable to get comparator for class "%s"', $dto::class));
    }
}
