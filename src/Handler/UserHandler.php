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
use App\Manager\TemporaryFilesManager;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

final readonly class UserHandler
{
    public function __construct(
        private TemporaryFilesManager $temporaryFilesManager,
    ) {
    }

    public function hasToUploadNewImage(string $tempFilePath, User $user): bool
    {
        if ($user->getImage()->getName() || !$user->getOAuth()) {
            return false;
        }

        $currentHash = $user->getImageSystemHash();
        if (null === $currentHash) {
            return true;
        }

        $newHash = md5_file($tempFilePath);

        return $newHash !== $currentHash;
    }

    public function uploadFile(User $user, string $tempFilePath): void
    {
        if (null === $user->getOAuth()) {
            return;
        }

        $fileSize = filesize($tempFilePath);
        if (false === $fileSize || 0 === $fileSize) {
            $user->getOAuth()->setFacebookProfilePicture(null);
            $user->setImageSystemFile(null);

            return;
        }

        $hash = md5_file($tempFilePath);

        $mimeTypes = new MimeTypes();
        $contentType = $mimeTypes->guessMimeType($tempFilePath);
        $ext = match ($contentType) {
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/jpg', 'image/jpeg' => 'jpeg',
            default => throw new RuntimeException(\sprintf('Unable to find extension for mime type %s', $contentType)),
        };

        $filename = $user->getId() . '.' . $ext;

        $file = new UploadedFile($tempFilePath, $filename, $contentType, test: true);
        $user->setImageSystemHash($hash);
        $user->setImageSystemFile($file);
    }

    public function createTempFile(): string
    {
        return $this->temporaryFilesManager->create();
    }
}
