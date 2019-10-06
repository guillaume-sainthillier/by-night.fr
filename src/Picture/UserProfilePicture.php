<?php

namespace App\Picture;

use App\Entity\User;
use App\Twig\AssetExtension;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Asset\Packages;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserProfilePicture
{
    /**
     * @var CacheManager
     */
    private $cacheManager;

    /** @var UploaderHelper */
    private $helper;

    /** @var Packages */
    private $packages;

    /** @var AssetExtension */
    private $assetExtension;

    public function __construct(CacheManager $cacheManager, UploaderHelper $helper, Packages $packages, AssetExtension $assetExtension)
    {
        $this->assetExtension = $assetExtension;
        $this->cacheManager = $cacheManager;
        $this->helper = $helper;
        $this->packages = $packages;
    }

    public function getOriginalProfilePicture(User $user)
    {
        if ($user->getPath()) {
            return $this->packages->getUrl(
                $this->helper->asset($user, 'imageFile'),
                'cdn'
            );
        }

        if ($user->getSystemPath()) {
            return $this->packages->getUrl(
                $this->helper->asset($user, 'imageSystemFile'),
                'cdn'
            );
        }

        $info = $user->getInfo();
        if ($info) {
            if (null !== $info->getFacebookProfilePicture()) {
                return $info->getFacebookProfilePicture();
            } elseif (null !== $info->getTwitterProfilePicture()) {
                return $info->getTwitterProfilePicture();
            } elseif (null !== $info->getGoogleProfilePicture()) {
                return $info->getGoogleProfilePicture();
            }
        }

        return $this->packages->getUrl(AssetExtension::ASSET_PREFIX . '/images/empty_user.png');
    }

    public function getProfilePicture(User $user, array $params = [])
    {
        if ($user->getPath()) {
            return $this->assetExtension->thumb($this->helper->asset($user, 'imageFile'), $params);
        }

        if ($user->getSystemPath()) {
            return $this->assetExtension->thumb($this->helper->asset($user, 'imageSystemFile'), $params);
        }

        $info = $user->getInfo();
        if ($info) {
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
            $this->packages->getUrl(AssetExtension::ASSET_PREFIX . '/images/empty_user.png', 'local'),
            $params
        );
    }
}
