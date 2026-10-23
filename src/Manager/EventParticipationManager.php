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
use App\Outing\OutingState;
use App\Repository\UserEventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The members' calendars (UserEvent) and the counters of the events they follow (Event::$participations and
 * $interests). The counters are recounted from the calendars as flushed, never moved by one: a missed or doubled update
 * cannot leave them wrong for good. Every flush that changes a calendar recounts its events (UserEventCountListener),
 * the back office's included.
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
        // What they will be told changed, from now on (OutingChangeNotifier); never the author or the member managing its
        // publisher, who make the changes
        if ($going && $event->getUser() !== $user && !($event->getPublisher()?->isManagedBy($user) ?? false)) {
            $userEvent->setKnownState(OutingState::fromEvent($event));
        }

        $this->entityManager->flush();
    }

    /**
     * Stores the counters of the events from their calendars as flushed, and sets them on the entities as loaded, so
     * the next flush does not write them again. Runs its own UPDATEs: callable from a postFlush listener.
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

        if ([] === $byId) {
            return;
        }

        $counts = $this->userEventRepository->countByEvents(array_keys($byId));

        // One UPDATE per pair of counts: a flush usually touches a single event
        $byCounts = [];
        foreach ($byId as $id => $event) {
            $participations = $counts[$id]['participations'] ?? 0;
            $interests = $counts[$id]['interests'] ?? 0;
            $byCounts[$participations . ':' . $interests] ??= ['participations' => $participations, 'interests' => $interests, 'ids' => []];
            $byCounts[$participations . ':' . $interests]['ids'][] = $id;
        }

        foreach ($byCounts as $group) {
            $this->entityManager
                ->createQueryBuilder()
                ->update(Event::class, 'e')
                ->set('e.participations', ':participations')
                ->set('e.interests', ':interests')
                ->where('e.id IN (:ids)')
                ->setParameter('participations', $group['participations'])
                ->setParameter('interests', $group['interests'])
                ->setParameter('ids', $group['ids'])
                ->getQuery()
                ->execute();
        }

        // The response to this request reads the new counters (the "J'y vais" button)
        $unitOfWork = $this->entityManager->getUnitOfWork();
        foreach ($byId as $id => $event) {
            $participations = $counts[$id]['participations'] ?? 0;
            $interests = $counts[$id]['interests'] ?? 0;
            $event
                ->setParticipations($participations)
                ->setInterests($interests);
            if ($unitOfWork->isInIdentityMap($event)) {
                $unitOfWork->setOriginalEntityProperty(spl_object_id($event), 'participations', $participations);
                $unitOfWork->setOriginalEntityProperty(spl_object_id($event), 'interests', $interests);
            }
        }
    }
}
