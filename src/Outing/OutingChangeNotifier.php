<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Outing;

use App\Entity\User;
use App\Manager\MailerManager;
use App\Message\CheckOutingChanges;
use App\Repository\UserEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Tells the members following events what changed in them since they last heard of it (OutingChanges), by e-mail: a
 * cancellation, a venue or dates that leave out the day they may have chosen. What they know of each event
 * (UserEvent::$knownState) then becomes the event as it is, alert or not, so that the next change is told from there.
 *
 * One alert a day at most per member and event: a change after the day's alert is told the next morning, in one
 * alert with whatever else changed by then. Members who turned the alerts off are told nothing, but what they know
 * follows the event all the same, so that turning them on again does not bring back the changes of the meantime.
 */
final readonly class OutingChangeNotifier
{
    /** When the changes held back by the day's alert are told, the next day */
    private const string NEXT_ALERT = 'tomorrow 09:00';

    public function __construct(
        private UserEventRepository $userEventRepository,
        private EntityManagerInterface $entityManager,
        private MailerManager $mailerManager,
        private MessageBusInterface $messageBus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<int> $eventIds
     */
    public function notify(array $eventIds): void
    {
        $now = $this->clock->now();
        $today = $now->setTime(0, 0);
        $heldBack = [];

        foreach ($this->userEventRepository->findFollowersOfEvents($eventIds) as $outing) {
            $event = $outing->getEvent();
            $user = $outing->getUser();
            if (null === $event || null === $user) {
                continue;
            }

            // Hidden by its author, for now: nothing to tell until it is published again. Hidden because its source
            // no longer lists it is a change to tell
            if ($event->isDraft() && !$event->isRemovedAtSource()) {
                continue;
            }

            $state = OutingState::fromEvent($event);
            $known = $outing->getKnownState();
            $changes = null === $known ? [] : OutingChanges::between($known, $state, $today);
            if (null !== $known && [] !== $changes && $this->wantsAlerts($user)) {
                if (null !== $outing->getAlertedAt() && $outing->getAlertedAt() >= $today) {
                    $heldBack[(int) $event->getId()] = true;

                    continue;
                }

                $this->mailerManager->sendOutingChangedEmail($outing, $changes, $known);
                $outing->setKnownState($state)->setAlertedAt($now);
                // Sent is sent: a retry of the message after a failed e-mail does not send it again
                $this->entityManager->flush();

                continue;
            }

            $outing->setKnownState($state);
        }

        $this->entityManager->flush();

        if ([] !== $heldBack) {
            $delay = $now->modify(self::NEXT_ALERT)->getTimestamp() - $now->getTimestamp();
            $this->messageBus->dispatch(Envelope::wrap(new CheckOutingChanges(array_keys($heldBack)), [new DelayStamp($delay * 1000)]));
        }
    }

    private function wantsAlerts(User $user): bool
    {
        return $user->isOutingAlerts() && $user->isEnabled() && null !== $user->getEmail() && '' !== $user->getEmail();
    }
}
