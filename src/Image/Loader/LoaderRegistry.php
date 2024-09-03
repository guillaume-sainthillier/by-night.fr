<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Image\Loader;

use App\Contracts\ImageLoaderInterface;
use RuntimeException;

class LoaderRegistry
{
    /**
     * @param iterable<ImageLoaderInterface> $loaders
     */
    public function __construct(private readonly iterable $loaders)
    {
    }

    public function getLoader(array $params): ImageLoaderInterface
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($params)) {
                return $loader;
            }
        }

        throw new RuntimeException(\sprintf('No loaders support your parameters: %s', json_encode($params, \JSON_THROW_ON_ERROR)));
    }
}
