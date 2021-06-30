<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Contracts\EntityProviderInterface;

class EntityProviderHandler
{
    /** @var EntityProviderInterface[] */
    private $entityProviders;

    public function __construct(iterable $entityProviders)
    {
        $this->entityProviders = $entityProviders;
    }

    public function getEntityProvider(string $className): EntityProviderInterface
    {
        foreach ($this->entityProviders as $entityProvider) {
            if ($entityProvider->supports($className)) {
                return $entityProvider;
            }
        }

        throw new \RuntimeException(sprintf('Unable to get entity provider for class "%s"', $className));
    }
}
