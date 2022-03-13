<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Picture;

use App\Entity\User;
use App\Helper\AssetHelper;
use Symfony\Component\Asset\Packages;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserProfilePicture
{
    public function __construct(
        private UploaderHelper $helper,
        private Packages $packages,
        private AssetHelper $assetHelper
    ) {
    }

    public function getOriginalProfilePicture(User $user): string|null
    {
        [
            'path' => $path,
            'source' => $source
        ] = $this->getPicturePathAndSource($user);

        if ('upload' === $source) {
            return $this->packages->getUrl(
                $path,
                'aws'
            );
        }

        if ('local' === $source) {
            return $this->packages->getUrl($path);
        }

        return $path;
    }

    public function getProfilePicture(User $user, array $params = []): string|null
    {
        [
            'path' => $path,
            'source' => $source
        ] = $this->getPicturePathAndSource($user);

        if ('upload' === $source) {
            return $this->assetHelper->getThumbUrl($path, $params);
        }

        if ('local' === $source) {
            return $this->assetHelper->getThumbAssetUrl($path, $params);
        }

        // dist
        return $path;
    }

    public function getDefaultProfilePicture(array $params = []): string
    {
        return $this->assetHelper->getThumbAssetUrl(
            $this->packages->getUrl('build/images/empty_user.png', 'local'),
            $params
        );
    }

    public function getPicturePathAndSource(User $user): array
    {
        if ($user->getImage()->getName()) {
            return [
                'path' => $this->helper->asset($user, 'imageFile'),
                'source' => 'upload',
            ];
        }

        if ($user->getImageSystem()->getName()) {
            return [
                'path' => $this->helper->asset($user, 'imageSystemFile'),
                'source' => 'upload',
            ];
        }

        $info = $user->getOAuth();
        if (null !== $info) {
            if (null !== $info->getFacebookProfilePicture()) {
                return [
                    'path' => $info->getFacebookProfilePicture(),
                    'source' => 'dist',
                ];
            } elseif (null !== $info->getTwitterProfilePicture()) {
                return [
                    'path' => $info->getTwitterProfilePicture(),
                    'source' => 'dist',
                ];
            } elseif (null !== $info->getGoogleProfilePicture()) {
                return [
                    'path' => $info->getGoogleProfilePicture(),
                    'source' => 'dist',
                ];
            }
        }

        return [
            'path' => $this->packages->getUrl('build/images/empty_user.png', 'local'),
            'source' => 'local',
        ];
    }
}
