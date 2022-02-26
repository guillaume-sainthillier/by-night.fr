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
use App\Twig\AssetExtension;
use Symfony\Component\Asset\Packages;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserProfilePicture
{
    public function __construct(private UploaderHelper $helper, private Packages $packages, private AssetExtension $assetExtension)
    {
    }

    public function getOriginalProfilePicture(User $user): string
    {
        if ($user->getImage()->getName()) {
            return $this->packages->getUrl(
                $this->helper->asset($user, 'imageFile'),
                'aws'
            );
        }

        if ($user->getImageSystem()->getName()) {
            return $this->packages->getUrl(
                $this->helper->asset($user, 'imageSystemFile'),
                'aws'
            );
        }

        $info = $user->getOAuth();
        if (null !== $info) {
            if (null !== $info->getFacebookProfilePicture()) {
                return $info->getFacebookProfilePicture();
            } elseif (null !== $info->getTwitterProfilePicture()) {
                return $info->getTwitterProfilePicture();
            } elseif (null !== $info->getGoogleProfilePicture()) {
                return $info->getGoogleProfilePicture();
            }
        }

        return $this->packages->getUrl('build/images/empty_user.png');
    }

    public function getProfilePicture(User $user, array $params = []): string
    {
        if ($user->getImage()->getName()) {
            return $this->assetExtension->thumb($this->helper->asset($user, 'imageFile'), $params);
        }

        if ($user->getImageSystem()->getName()) {
            return $this->assetExtension->thumb($this->helper->asset($user, 'imageSystemFile'), $params);
        }

        $info = $user->getOAuth();
        if (null !== $info) {
            if (null !== $info->getFacebookProfilePicture()) {
                return $info->getFacebookProfilePicture();
            } elseif (null !== $info->getTwitterProfilePicture()) {
                return $info->getTwitterProfilePicture();
            } elseif (null !== $info->getGoogleProfilePicture()) {
                return $info->getGoogleProfilePicture();
            }
        }

        return $this->getDefaultProfilePicture($params);
    }

    public function getDefaultProfilePicture(array $params = []): string
    {
        return $this->assetExtension->thumbAsset(
            $this->packages->getUrl('build/images/empty_user.png', 'local'),
            $params
        );
    }
}
