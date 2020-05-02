<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Entity\User;
use App\File\DeletableFile;
use RuntimeException;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserHandler
{
    private string $tempPath;
    private string $webDir;

    private UploaderHelper $helper;

    public function __construct(UploaderHelper $helper, string $webDir, string $tempPath)
    {
        $this->helper = $helper;
        $this->tempPath = $tempPath;
        $this->webDir = $webDir;
    }

    public function hasToUploadNewImage(?string $newContent, User $user)
    {
        if ($user->getImage()->getName() || !$user->getInfo()) {
            return false;
        }

        if (!$user->getImageSystem()->getName()) {
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
        if (null === $user->getInfo()) {
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
                    throw new RuntimeException(sprintf('Unable to find extension for mime type %s', $contentType));
            }

            $filename = $user->getId() . '.' . $ext;
            $tempPath = $this->tempPath . \DIRECTORY_SEPARATOR . $filename;
            $octets = \file_put_contents($tempPath, $content);

            if ($octets > 0) {
                $file = new DeletableFile($tempPath, $filename, null, null, true);
                $user->setImageSystemFile($file);
            } else {
                $user->setImageSystemFile(null);
            }
        }
    }
}
