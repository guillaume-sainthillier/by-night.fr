<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 23/12/2016
 * Time: 19:29.
 */

namespace App\Handler;

use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
            $url      = $user->getInfo()->getFacebookProfilePicture();
            $path     = \parse_url($url, PHP_URL_PATH);
            $ext      = \pathinfo($path, PATHINFO_EXTENSION);
            $filename = \sha1(\uniqid(\mt_rand(), true)) . '.' . $ext;

            $tempPath = $this->tempPath . '/' . $filename;
            $octets   = \file_put_contents($tempPath, $content);

            if ($octets > 0) {
                $file = new UploadedFile($tempPath, $filename, null, null, true);
                $user->setSystemPath($filename);
                $user->setImageSystemFile($file);
            } else {
                $user->setImageSystemFile(null)->setSystemPath(null);
            }
        }
    }
}
