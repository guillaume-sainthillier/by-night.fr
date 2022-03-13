<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Image\Loader;

use App\Entity\Event;
use App\Picture\EventProfilePicture;

class EventLoader extends AbstractImageLoader
{
    public function __construct(
        private LocalAssetLoader $localAssetLoader,
        private EventProfilePicture $eventProfilePicture,
    ) {
    }

    public function getDefaultParams(array $params): array
    {
        [
            'event' => $event,
        ] = $params;

        \assert($event instanceof Event);

        ['path' => $path, 'source' => $source] = $this->eventProfilePicture->getPicturePathAndSource($event);
        $params['path'] = $path;
        $defaultParams = [
            'aspectRatio' => 16 / 9,
            'placeholderObjectFit' => 'cover',
            'objectFit' => 'contain',
            'wrapperAttr' => [
                'class' => 'image-wrapper-placeholder-cover',
            ],
        ];

        if ('local' === $source) {
            return $this->localAssetLoader->getDefaultParams(array_merge($defaultParams, $params));
        }

        [
            'originalWidth' => $originalWidth,
            'originalHeight' => $originalHeight,
            'originalFormat' => $originalFormat,
        ] = $params;

        if ((!$originalWidth || !$originalHeight) && $event->getImage()->getDimensions()) {
            $originalWidth = (int) $event->getImage()->getDimensions()[0];
            $originalHeight = (int) $event->getImage()->getDimensions()[1];
        } elseif ((!$originalWidth || !$originalHeight) && $event->getImageSystem()->getDimensions()) {
            $originalWidth = (int) $event->getImageSystem()->getDimensions()[0];
            $originalHeight = (int) $event->getImageSystem()->getDimensions()[1];
        }

        if (!$originalFormat) {
            $originalFormat = $this->guessExtensionFromPath($path);
        }

        dump([
            'path' => $path,
            'originalWidth' => $originalWidth,
            'originalHeight' => $originalHeight,
            'originalFormat' => $originalFormat,
        ]);

        return array_merge($defaultParams, [
            'originalWidth' => $originalWidth,
            'originalHeight' => $originalHeight,
            'originalFormat' => $originalFormat,
        ]);
    }

    public function getUrl(array $params): string
    {
        [
            'event' => $event,
            'width' => $width,
            'height' => $height,
            'format' => $format,
            'loaderOptions' => $loaderOptions,
        ] = $params;

        \assert($event instanceof Event);

        return $this->eventProfilePicture->getPicture($event, array_filter([
            'w' => $width,
            'h' => $height,
            'fm' => 'jpg' === $format ? 'pjpg' : $format,
            'q' => $loaderOptions['quality'] ?? null,
            'fit' => $loaderOptions['fit'] ?? null,
        ]));
    }

    public function supports(array $params): bool
    {
        return ($params['loader'] ?? null) === 'event';
    }
}
