<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Contracts\EntityFactoryInterface;

class EntityFactoryHandler
{
    /** @var EntityFactoryInterface[] */
    private $entityFactories;

    public function __construct(iterable $entityFactories)
    {
        $this->entityFactories = $entityFactories;
    }

    public function getFactory(string $className): EntityFactoryInterface
    {
        foreach ($this->entityFactories as $entityFactory) {
            if ($entityFactory->supports($className)) {
                return $entityFactory;
            }
        }

        throw new \RuntimeException(sprintf('Unable to get entity factory for class "%s"', $className));
    }
}
