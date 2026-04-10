<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Picture;

use App\Entity\User;
use Silarhi\PicassoBundle\Service\ImageHelperInterface;
use Symfony\Component\Asset\Packages;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

final readonly class UserProfilePicture
{
    public function __construct(
        private UploaderHelper $helper,
        private Packages $packages,
        private ImageHelperInterface $imageHelper,
    ) {
    }

    public function getOriginalProfilePicture(User $user): ?string
    {
        [
            'path' => $path,
            'source' => $source,
        ] = $this->getPicturePathAndSource($user);

        if ('upload' === $source) {
            return $this->packages->getUrl(
                $path,
                'aws'
            );
        }

        if ('local' === $source) {
            return $this->packages->getUrl($path);
        }

        return $path;
    }

    public function getProfilePicture(User $user, ?int $width = null, ?int $height = null, ?string $fit = null, ?int $dpr = null): ?string
    {
        $data = $this->getPicturePathAndSource($user);

        if (null === $data['loader']) {
            // External URL (OAuth provider)
            return $data['path'];
        }

        return $this->imageHelper->imageUrl(
            $data['path'],
            width: $width,
            height: $height,
            fit: $fit,
            dpr: $dpr,
            loader: $data['loader'],
            context: [
                'entity' => $data['entity'],
                'field' => $data['field'],
            ],
        );
    }

    public function getPicturePathAndSource(User $user): array
    {
        if ($user->getImage()->getName()) {
            return [
                'path' => $this->helper->asset($user, 'imageFile'),
                'source' => 'upload',
                'entity' => $user,
                'field' => 'imageFile',
                'loader' => 'vich',
            ];
        }

        if ($user->getImageSystem()->getName()) {
            return [
                'path' => $this->helper->asset($user, 'imageSystemFile'),
                'source' => 'upload',
                'entity' => $user,
                'field' => 'imageSystemFile',
                'loader' => 'vich',
            ];
        }

        $info = $user->getOAuth();
        if (null !== $info) {
            if (null !== $info->getFacebookId()) {
                return [
                    'path' => \sprintf('https://graph.facebook.com/%s/picture?type=large', $info->getFacebookId()),
                    'source' => 'dist',
                    'entity' => null,
                    'field' => null,
                    'loader' => null,
                ];
            }

            if (null !== $info->getTwitterProfilePicture()) {
                return [
                    'path' => $info->getTwitterProfilePicture(),
                    'source' => 'dist',
                    'entity' => null,
                    'field' => null,
                    'loader' => null,
                ];
            }

            if (null !== $info->getGoogleProfilePicture()) {
                return [
                    'path' => $info->getGoogleProfilePicture(),
                    'source' => 'dist',
                    'entity' => null,
                    'field' => null,
                    'loader' => null,
                ];
            }
        }

        return [
            'path' => $this->packages->getUrl('build/images/empty_user.png', 'local'),
            'source' => 'local',
            'entity' => null,
            'field' => null,
            'loader' => 'filesystem',
        ];
    }
}
