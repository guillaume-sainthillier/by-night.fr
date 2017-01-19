<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 11/10/2016
 * Time: 18:48
 */

namespace TBN\MainBundle\Picture;


use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use TBN\UserBundle\Entity\User;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserProfilePicture
{
    /**
     * @var CacheManager $cacheManager
     */
    private $cacheManager;

    /** @var UploaderHelper $cacheManager
     */
    private $helper;

    public function __construct(CacheManager $cacheManager, UploaderHelper $helper)
    {
        $this->cacheManager = $cacheManager;
        $this->helper = $helper;
    }

    public function getProfilePicture(User $user, $thumb = 'thumb_user') {
        if($user->getPath()) {
            return $this->cacheManager->getBrowserPath($this->helper->asset($user, 'imageFile'), $thumb);
        }

        if($user->getSystemPath()) {
            return $this->cacheManager->getBrowserPath($this->helper->asset($user, 'imageSystemFile'), $thumb);
        }

        $info = $user->getInfo();
        if($info) {
            if ($info->getFacebookProfilePicture() !== null) {
                return $info->getFacebookProfilePicture();
            } elseif ($info->getTwitterProfilePicture() !== null) {
                return $info->getTwitterProfilePicture();
            } elseif ($info->getGoogleProfilePicture() !== null) {
                return $info->getGoogleProfilePicture();
            }
        }

        return $this->cacheManager->getBrowserPath('img/empty_user.png', $thumb);
    }
}
