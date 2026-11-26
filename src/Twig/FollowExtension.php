<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Entity\Place;
use App\Entity\Publisher;
use App\Entity\User;
use App\Repository\FollowRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

/**
 * The "Suivre" button of a publisher or a venue (<twig:FollowButton>): whether the member follows it, and by how many.
 */
final readonly class FollowExtension
{
    public function __construct(
        private FollowRepository $followRepository,
        private Security $security,
    ) {
    }

    /**
     * What it is, for follow.ts to call the API's address that follows it (PUT /api/publishers/{id}/follow or
     * /api/places/{id}/follow).
     */
    #[AsTwigFunction(name: 'follow_kind')]
    public function followKind(Publisher|Place $target): string
    {
        return $target instanceof Publisher ? 'publisher' : 'place';
    }

    #[AsTwigFunction(name: 'is_following')]
    public function isFollowing(Publisher|Place $target): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User && null !== $this->followRepository->findOneByTarget($user, $target);
    }

    #[AsTwigFunction(name: 'count_followers')]
    public function countFollowers(Publisher|Place $target): int
    {
        return $this->followRepository->countFollowers($target);
    }
}
