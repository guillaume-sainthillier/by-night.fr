<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
    private UploaderHelper $helper;

    private Packages $packages;

    private AssetExtension $assetExtension;

    public function __construct(UploaderHelper $helper, Packages $packages, AssetExtension $assetExtension)
    {
        $this->assetExtension = $assetExtension;
        $this->helper = $helper;
        $this->packages = $packages;
    }

    public function getOriginalProfilePicture(User $user)
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

        $info = $user->getInfo();
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

    public function getProfilePicture(User $user, array $params = [])
    {
        if ($user->getImage()->getName()) {
            return $this->assetExtension->thumb($this->helper->asset($user, 'imageFile'), $params);
        }

        if ($user->getImageSystem()->getName()) {
            return $this->assetExtension->thumb($this->helper->asset($user, 'imageSystemFile'), $params);
        }

        $info = $user->getInfo();
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

    public function getDefaultProfilePicture(array $params = [])
    {
        return $this->assetExtension->thumbAsset(
            $this->packages->getUrl('build/images/empty_user.png', 'local'),
            $params
        );
    }
}
