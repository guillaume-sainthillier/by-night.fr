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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * The "Suivre" button of a publisher or a venue (<twig:FollowButton>): whether the member follows it, and by how many.
 */
final readonly class FollowExtension
{
    public function __construct(
        private FollowRepository $followRepository,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * The API's address that follows it, or stops following it.
     */
    #[AsTwigFunction(name: 'follow_path')]
    public function followPath(Publisher|Place $target): string
    {
        return $this->urlGenerator->generate($target instanceof Publisher ? 'api_publisher_follow' : 'api_place_follow', ['id' => $target->getId()]);
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
