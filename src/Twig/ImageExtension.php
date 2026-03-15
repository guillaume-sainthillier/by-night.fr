<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Dto\EventDto;
use App\Entity\Event;
use App\Entity\User;
use App\Picture\EventProfilePicture;
use App\Picture\UserProfilePicture;
use Twig\Attribute\AsTwigFunction;

final class ImageExtension
{
    public function __construct(
        private readonly EventProfilePicture $eventProfilePicture,
        private readonly UserProfilePicture $userProfilePicture,
    ) {
    }

    /**
     * @return array{loader: string, src: string|null, context: array{entity: Event|EventDto|null, field: string|null}}
     */
    #[AsTwigFunction(name: 'event_picture')]
    public function eventPicture(Event|EventDto $event): array
    {
        $data = $this->eventProfilePicture->getPicturePathAndSource($event);

        return [
            'loader' => $data['loader'],
            'src' => 'filesystem' === $data['loader'] ? $data['path'] : null,
            'context' => [
                'entity' => $data['entity'],
                'field' => $data['field'],
            ],
        ];
    }

    /**
     * @return array{loader: string|null, src: string|null, unoptimized: bool, context: array{entity: User|null, field: string|null}}
     */
    #[AsTwigFunction(name: 'user_picture')]
    public function userPicture(User $user): array
    {
        $data = $this->userProfilePicture->getPicturePathAndSource($user);

        return [
            'loader' => $data['loader'],
            'src' => 'vich' !== $data['loader'] ? $data['path'] : null,
            'unoptimized' => null === $data['loader'],
            'context' => [
                'entity' => $data['entity'],
                'field' => $data['field'],
            ],
        ];
    }
}
