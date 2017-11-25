<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 23/12/2016
 * Time: 19:29.
 */

namespace AppBundle\Handler;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use AppBundle\Entity\User;

class UserHandler
{
    private $tempPath;

    public function __construct($tempPath)
    {
        $this->tempPath = $tempPath;
    }

    public function hasToDownloadImage($newURL, User $user)
    {
        return $newURL && (
                !$user->getSystemPath() ||
                ($user->getInfo() && $user->getInfo()->getFacebookProfilePicture() != $newURL)
            );
    }

    public function uploadFile(User $user, $content)
    {
        if (!$user->getInfo()) {
            return;
        }

        if (!$content) {
            $user->getInfo()->setFacebookProfilePicture(null);
        } else {
            $url = $user->getInfo()->getFacebookProfilePicture();
            //En cas d'url du type:  http://u.rl/image.png?params
            $ext = preg_replace("/(\?|_)(.*)$/", '', pathinfo($url, PATHINFO_EXTENSION));

            $filename = sha1(uniqid(mt_rand(), true)) . '.' . $ext;

            $tempPath = $this->tempPath . '/' . $filename;
            $octets   = file_put_contents($tempPath, $content);

            if ($octets > 0) {
                $file = new UploadedFile($tempPath, $filename, null, null, false, true);
                $user->setSystemPath($filename);
                $user->setImageSystemFile($file);
            } else {
                $user->setImageSystemFile(null)->setSystemPath(null);
            }
        }
    }
}
