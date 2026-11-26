<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Follow;

use App\Entity\Follow;
use App\Entity\Place;
use App\Entity\Publisher;
use App\Entity\User;
use App\Repository\FollowRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A member follows a publisher or a venue, or stops following it: its new events are e-mailed to them from then on
 * (FollowAlertSender).
 */
final readonly class FollowManager
{
    public function __construct(
        private FollowRepository $followRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function follow(User $user, Publisher|Place $target, bool $following): void
    {
        $follow = $this->followRepository->findOneByTarget($user, $target);
        if ($following && null === $follow) {
            $this->entityManager->persist(new Follow()->setUser($user)->setTarget($target));
        } elseif (!$following && null !== $follow) {
            $this->entityManager->remove($follow);
        }

        $this->entityManager->flush();
    }
}
