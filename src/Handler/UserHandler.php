<?php

namespace App\Handler;

use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserHandler
{
    private $tempPath;
    private $webDir;

    /** @var UploaderHelper
     */
    private $helper;

    public function __construct(UploaderHelper $helper, string $webDir, string $tempPath)
    {
        $this->helper = $helper;
        $this->tempPath = $tempPath;
        $this->webDir = $webDir;
    }

    public function hasToUploadNewImage(?string $newContent, User $user)
    {
        if ($user->getPath() || !$user->getInfo()) {
            return false;
        }

        if (!$user->getSystemPath()) {
            return true;
        }

        $image = $this->helper->asset($user, 'imageSystemFile');
        if (null === $image) {
            return true;
        }

        $imagePath = $this->webDir . \DIRECTORY_SEPARATOR . ltrim($image, \DIRECTORY_SEPARATOR);
        if (!file_exists($imagePath)) {
            return true;
        }

        return md5_file($imagePath) !== md5($newContent);
    }

    public function uploadFile(User $user, $content, $contentType)
    {
        if (!$user->getInfo()) {
            return;
        }

        if (!$content) {
            $user->getInfo()->setFacebookProfilePicture(null);
        } else {
            switch ($contentType) {
                case 'image/gif':
                    $ext = 'gif';
                    break;
                case 'image/png':
                    $ext = 'png';
                    break;
                case 'image/jpg':
                case 'image/jpeg':
                    $ext = 'jpeg';
                    break;
                default:
                    throw new \RuntimeException(sprintf('Unable to find extension for mime type %s', $contentType));
            }

            $filename = $user->getId() . '.' . $ext;
            $tempPath = $this->tempPath . \DIRECTORY_SEPARATOR . $filename;
            $octets = \file_put_contents($tempPath, $content);

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
