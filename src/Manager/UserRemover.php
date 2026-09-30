<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Deletes a member's account, whether they close it from their profile or an admin deletes it: their
 * comments and favourites go with it (the counters of the favourite events drop), their events are
 * deleted too or stay online without an author. Comments, favourites and events reference the member
 * without any ON DELETE rule, so removing the account alone is refused by the database.
 */
final readonly class UserRemover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
        private CommentRepository $commentRepository,
    ) {
    }

    public function remove(User $user, bool $withEvents): void
    {
        foreach ($this->eventRepository->findBy(['user' => $user]) as $event) {
            if ($withEvents) {
                $this->entityManager->remove($event);
            } else {
                $event->setUser(null);
            }
        }

        foreach ($user->getUserEvents() as $userEvent) {
            $event = $userEvent->getEvent();
            if ($userEvent->getGoing()) {
                $event->setParticipations($event->getParticipations() - 1);
            } else {
                $event->setInterests($event->getInterests() - 1);
            }

            $this->entityManager->remove($userEvent);
        }

        foreach ($this->commentRepository->findAllByUser($user) as $comment) {
            $this->entityManager->remove($comment);
        }

        $this->entityManager->flush();

        // TODO: Optimize flush & check constraints
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
