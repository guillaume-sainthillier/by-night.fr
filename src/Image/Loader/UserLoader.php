<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Image\Loader;

use App\Entity\User;
use App\Picture\UserProfilePicture;

final class UserLoader extends AbstractImageLoader
{
    public function __construct(
        private readonly LocalAssetLoader $localAssetLoader,
        private readonly UrlLoader $urlLoader,
        private readonly UserProfilePicture $userProfilePicture,
    ) {
    }

    public function getDefaultParams(array $params): array
    {
        [
            'user' => $user,
        ] = $params;

        \assert($user instanceof User);

        [
            'path' => $path,
            'source' => $source,
        ] = $this->userProfilePicture->getPicturePathAndSource($user);

        $params['path'] = $path;
        $defaultParams = [
            'aspectRatio' => 1,
            'placeholderObjectFit' => 'cover',
            'objectFit' => 'contain',
            'wrapperAttr' => [
                'class' => 'image-wrapper-placeholder-cover',
            ],
        ];

        if ('local' === $source) {
            return $this->localAssetLoader->getDefaultParams(array_merge($defaultParams, $params));
        }

        if ('dist' === $source) {
            return $this->urlLoader->getDefaultParams(array_merge($defaultParams, $params));
        }

        [
            'originalWidth' => $originalWidth,
            'originalHeight' => $originalHeight,
            'originalFormat' => $originalFormat,
        ] = $params;

        if ((!$originalWidth || !$originalHeight) && $user->getImage()->getDimensions()) {
            $originalWidth = $user->getImage()->getDimensions()[0];
            $originalHeight = $user->getImage()->getDimensions()[1];
        } elseif ((!$originalWidth || !$originalHeight) && $user->getImageSystem()->getDimensions()) {
            $originalWidth = $user->getImageSystem()->getDimensions()[0];
            $originalHeight = $user->getImageSystem()->getDimensions()[1];
        }

        if (!$originalFormat && $user->getImage()->getMimeType()) {
            $originalFormat = $this->guessExtensionFromPath($user->getImage()->getMimeType());
        }

        if (!$originalFormat && $user->getImageSystem()->getMimeType()) {
            $originalFormat = $this->guessExtensionFromPath($user->getImageSystem()->getMimeType());
        }

        if (!$originalFormat) {
            $originalFormat = $this->guessExtensionFromPath($path);
        }

        return [...$defaultParams, 'originalWidth' => $originalWidth, 'originalHeight' => $originalHeight, 'originalFormat' => $originalFormat ?? 'jpg'];
    }

    public function getUrl(array $params): string
    {
        [
            'user' => $user,
            'width' => $width,
            'height' => $height,
            'format' => $format,
            'loaderOptions' => $loaderOptions,
        ] = $params;

        \assert($user instanceof User);

        return $this->userProfilePicture->getProfilePicture($user, array_filter([
            'w' => $width,
            'h' => $height,
            'fm' => 'jpg' === $format ? 'pjpg' : $format,
            'q' => $loaderOptions['quality'] ?? null,
            'fit' => $loaderOptions['fit'] ?? null,
        ]));
    }

    public function supports(array $params): bool
    {
        return ($params['loader'] ?? null) === 'user';
    }
}
