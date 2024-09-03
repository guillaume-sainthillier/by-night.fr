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

abstract class AbstractImageLoader implements ImageLoaderInterface
{
    protected function guessExtensionFromMimeType(string $mimeType): ?string
    {
        $mimeType = mb_strtolower($mimeType);

        return match ($mimeType) {
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/jpg',
            'image/jpeg' => 'jpeg',
            default => null,
        };
    }

    protected function guessExtensionFromPath(string $path): ?string
    {
        $path = mb_strtolower($path);

        return match (true) {
            str_ends_with($path, '.jpg'),
            str_ends_with($path, '.jpeg') => 'jpg',
            str_ends_with($path, '.png') => 'png',
            str_ends_with($path, '.gif') => 'gif',
            default => null,
        };
    }

    public function getDefaultParams(array $params): array
    {
        return [];
    }
}
