<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 11/10/2016
 * Time: 18:48.
 */

namespace App\Picture;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use App\Entity\User;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserProfilePicture
{
    /**
     * @var CacheManager
     */
    private $cacheManager;

    /** @var UploaderHelper
     */
    private $helper;

    public function __construct(CacheManager $cacheManager, UploaderHelper $helper)
    {
        $this->cacheManager = $cacheManager;
        $this->helper       = $helper;
    }

    public function getProfilePicture(User $user, $thumb = 'thumb_user')
    {
        if ($user->getPath()) {
            return $this->cacheManager->getBrowserPath($this->helper->asset($user, 'imageFile'), $thumb);
        }

        if ($user->getSystemPath()) {
            return $this->cacheManager->getBrowserPath($this->helper->asset($user, 'imageSystemFile'), $thumb);
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

        return $this->cacheManager->getBrowserPath('img/empty_user.png', $thumb);
    }
}
