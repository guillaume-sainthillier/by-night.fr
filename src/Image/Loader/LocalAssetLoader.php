<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Image\Loader;

use App\Helper\AssetHelper;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class LocalAssetLoader extends AbstractImageLoader
{
    public function __construct(
        private readonly AssetHelper $assetHelper,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDirectory,
    ) {
    }

    #[Override]
    public function getDefaultParams(array $params): array
    {
        [
            'originalWidth' => $originalWidth,
            'originalHeight' => $originalHeight,
            'originalFormat' => $originalFormat,
            'path' => $path,
        ] = $params;

        if (null === $originalFormat && null !== $path) {
            $originalFormat = $this->guessExtensionFromPath($path);
        }

        if (!$originalWidth || !$originalHeight) {
            $absolutePath = rtrim($this->publicDirectory, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . ltrim((string) $path, '/');
            $dimensions = @getimagesize($absolutePath);
            if (!$dimensions) {
                return $params;
            }

            $originalWidth = $dimensions[0];
            $originalHeight = $dimensions[1];
        }

        return [
            'originalWidth' => $originalWidth,
            'originalHeight' => $originalHeight,
            'originalFormat' => $originalFormat ?? 'jpg',
        ];
    }

    public function getUrl(array $params): string
    {
        [
            'path' => $path,
            'width' => $width,
            'height' => $height,
            'format' => $format,
            'loaderOptions' => $loaderOptions,
        ] = $params;

        return $this->assetHelper->getThumbAssetUrl($path, array_filter([
            'w' => $width,
            'h' => $height,
            'fm' => 'jpg' === $format ? 'pjpg' : $format,
            'q' => $loaderOptions['quality'] ?? null,
        ]));
    }

    public function supports(array $params): bool
    {
        return \in_array($params['loader'] ?? null, ['asset', null], true);
    }
}
