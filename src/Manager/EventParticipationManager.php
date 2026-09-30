<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Repository\UserEventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The members' calendars (UserEvent) and the counters of the events they follow (Event::$participations and
 * $interests). The counters are recounted from the calendars as flushed, never moved by one: a missed or doubled update
 * cannot leave them wrong for good.
 */
final readonly class EventParticipationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserEventRepository $userEventRepository,
    ) {
    }

    /**
     * A member's "J'y vais" on an event, or its removal: the event stays in their calendar, the counters follow. Flushes.
     */
    public function participate(User $user, Event $event, bool $going): void
    {
        $userEvent = null === $event->getId() ? null : $this->userEventRepository->findOneBy(['user' => $user, 'event' => $event]);
        if (null === $userEvent) {
            // setEvent() rather than Event::addUserEvent(), which would load every calendar holding the event
            $userEvent = new UserEvent()->setUser($user)->setEvent($event);
            $this->entityManager->persist($userEvent);
        }

        $userEvent->setGoing($going);
        $this->entityManager->flush();

        $this->recount([$event]);
        $this->entityManager->flush();
    }

    /**
     * Sets the counters of the events from their calendars as flushed. Does not flush.
     *
     * @param iterable<Event> $events
     */
    public function recount(iterable $events): void
    {
        $byId = [];
        foreach ($events as $event) {
            if (null !== $event->getId()) {
                $byId[$event->getId()] = $event;
            }
        }

        $counts = $this->userEventRepository->countByEvents(array_keys($byId));
        foreach ($byId as $id => $event) {
            $event
                ->setParticipations($counts[$id]['participations'] ?? 0)
                ->setInterests($counts[$id]['interests'] ?? 0);
        }
    }
}
