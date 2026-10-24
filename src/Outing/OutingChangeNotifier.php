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
use App\Entity\UserEvent;
use App\Manager\MailerManager;
use App\Repository\EventRepository;
use App\Repository\UserEventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Tells the members following events what changed in them since they last heard of it (OutingChanges), by e-mail: a
 * cancellation, a venue or dates that leave out the day they may have chosen. What they know of each event
 * (UserEvent::$knownState) then becomes the event as it is, alert or not, so that the next change is told from there.
 *
 * The events are those their change flagged (Event::$flaggedForAlertAt, OutingChangeListener), told a few times a day
 * by app:outing-alerts:send: a run takes every event flagged before it started, then clears its flag, unless a change
 * came while it ran (told by the next run) or a follower is still to hear of it. Run again, it sends nothing twice: each
 * alert is recorded as soon as it left.
 *
 * One alert a day at most per member and event: a change after the day's alert is told by the first run of the next
 * day, in one alert with whatever else changed by then. Members who turned the alerts off are told nothing, but what
 * they know follows the event all the same, so that turning them on again does not bring back the changes of the
 * meantime.
 */
final readonly class OutingChangeNotifier
{
    /** Events per batch: their followers in one query, the entity manager cleared after */
    private const int BATCH_SIZE = 500;

    public function __construct(
        private EventRepository $eventRepository,
        private UserEventRepository $userEventRepository,
        private EntityManagerInterface $entityManager,
        private MailerManager $mailerManager,
    ) {
    }

    /**
     * @param DateTimeImmutable $now when the run starts: the events flagged before are told, those flagged since wait
     *                               for the next run
     */
    public function sendFlagged(DateTimeImmutable $now): OutingAlertRun
    {
        $run = new OutingAlertRun();

        foreach ($this->eventRepository->findFlaggedForAlert($now, self::BATCH_SIZE)->getChunkResults() as $events) {
            $eventIds = array_column($events, 'id');
            $untold = $this->notify($eventIds, $now, $run);
            $this->eventRepository->clearAlertFlags(array_values(array_diff($eventIds, $untold)), $now);
            $this->entityManager->clear();
        }

        return $run;
    }

    /**
     * @param list<int> $eventIds
     *
     * @return list<int> the events with a change still to tell: hidden for now, held back until tomorrow, or whose
     *                   e-mail could not leave
     */
    private function notify(array $eventIds, DateTimeImmutable $now, OutingAlertRun $run): array
    {
        $today = $now->setTime(0, 0);
        $untold = [];

        foreach ($this->userEventRepository->findFollowersOfEvents($eventIds) as $outing) {
            $event = $outing->getEvent();
            $user = $outing->getUser();
            if (null === $event || null === $user) {
                continue;
            }

            // Hidden by its author, for now: told once published again, which flags it. Hidden because its source no
            // longer lists it is a change to tell
            if ($event->isDraft() && !$event->isRemovedAtSource()) {
                continue;
            }

            $state = OutingState::fromEvent($event);
            $known = $outing->getKnownState();
            $changes = null === $known ? [] : OutingChanges::between($known, $state, $today);
            if (null === $known || [] === $changes || !$this->wantsAlerts($user)) {
                $outing->setKnownState($state);

                continue;
            }

            if ($this->heardOfItToday($outing, $today)) {
                $untold[(int) $event->getId()] = true;

                continue;
            }

            try {
                $this->mailerManager->sendOutingChangedEmail($outing, $changes, $known);
            } catch (TransportExceptionInterface $e) {
                $run->recordFailure($outing, $e->getMessage());
                $untold[(int) $event->getId()] = true;

                continue;
            }

            $outing->setKnownState($state)->setAlertedAt($now);
            // Sent is sent: a run again after a crash does not send it twice
            $this->entityManager->flush();
            $run->recordSent();
        }

        $this->entityManager->flush();

        return array_keys($untold);
    }

    private function heardOfItToday(UserEvent $outing, DateTimeImmutable $today): bool
    {
        return null !== $outing->getAlertedAt() && $outing->getAlertedAt() >= $today;
    }

    private function wantsAlerts(User $user): bool
    {
        return $user->isOutingAlerts() && $user->isEnabled() && null !== $user->getEmail() && '' !== $user->getEmail();
    }
}
