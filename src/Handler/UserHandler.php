<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Entity\User;
use App\File\DeletableFile;
use RuntimeException;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

final readonly class UserHandler
{
    public function __construct(private UploaderHelper $helper, private string $webDir, private string $tempPath)
    {
    }

    public function hasToUploadNewImage(?string $newContent, User $user): bool
    {
        if ($user->getImage()->getName() || !$user->getOAuth()) {
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

        return md5_file($imagePath) !== md5((string) $newContent);
    }

    public function uploadFile(User $user, string $content, string $contentType): void
    {
        if (null === $user->getOAuth()) {
            return;
        }

        if ('' === $content) {
            $user->getOAuth()->setFacebookProfilePicture(null);
        } else {
            $ext = match ($contentType) {
                'image/gif' => 'gif',
                'image/png' => 'png',
                'image/jpg', 'image/jpeg' => 'jpeg',
                default => throw new RuntimeException(\sprintf('Unable to find extension for mime type %s', $contentType)),
            };

            $filename = $user->getId() . '.' . $ext;
            $tempPath = $this->tempPath . \DIRECTORY_SEPARATOR . $filename;
            $octets = file_put_contents($tempPath, $content);

            if ($octets > 0) {
                $file = new DeletableFile($tempPath, $filename, null, null, true);
                $user->setImageSystemFile($file);
            } else {
                $user->setImageSystemFile(null);
            }
        }
    }
}
