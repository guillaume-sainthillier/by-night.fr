<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Image\Loader;

final class UrlLoader extends AbstractImageLoader
{
    public function getUrl(array $params): string
    {
        return $params['url'];
    }

    public function supports(array $params): bool
    {
        return !empty($params['url']) || ($params['loader'] ?? null) === 'url';
    }

    public function getDefaultParams(array $params): array
    {
        [
            'originalFormat' => $originalFormat,
            'path' => $path,
        ] = $params;

        if (null === $originalFormat && null !== $path) {
            $originalFormat = $this->guessExtensionFromPath($path);
        }

        return [
            'path' => null,
            'url' => $params['path'] ?? null,
            'placeholder' => null,
            'outputPixelDensities' => [1],
            'formats' => [],
            'originalFormat' => $originalFormat ?? 'jpg',
        ];
    }
}
