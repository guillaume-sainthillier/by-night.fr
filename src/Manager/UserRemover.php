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
use App\Repository\UserEventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Deletes a member's account, whether they close it from their profile or an admin deletes it: their
 * comments and favourites go with it (the counters of the favourite events are recounted), their events are
 * deleted too or stay online without an author. Comments, favourites and events reference the member
 * without any ON DELETE rule, so removing the account alone is refused by the database.
 *
 * All of it or nothing, in one transaction: the account is deleted in the same flush as what references it
 * (Doctrine deletes the rows referencing a member before the member), then the counters are written.
 */
final readonly class UserRemover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
        private UserEventRepository $userEventRepository,
        private CommentRepository $commentRepository,
        private EventParticipationManager $eventParticipationManager,
    ) {
    }

    public function remove(User $user, bool $withEvents): void
    {
        $this->entityManager->wrapInTransaction(function () use ($user, $withEvents): void {
            foreach ($this->eventRepository->findBy(['user' => $user]) as $event) {
                if ($withEvents) {
                    $this->entityManager->remove($event);
                } else {
                    $event->setUser(null);
                }
            }

            // Loaded with their events, which the recount writes to: one query rather than one per favourite
            $followed = [];
            foreach ($this->userEventRepository->findByUserWithEvents($user) as $userEvent) {
                $followed[] = $userEvent->getEvent();
                $this->entityManager->remove($userEvent);
            }

            foreach ($this->commentRepository->findAllByUser($user) as $comment) {
                $this->entityManager->remove($comment);
            }

            $this->entityManager->remove($user);
            $this->entityManager->flush();

            // The events deleted with the account have lost their id: only the ones left are recounted
            $this->eventParticipationManager->recount(array_filter($followed));
            $this->entityManager->flush();
        });
    }
}
