<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Follow;

use App\Entity\Event;
use App\Entity\Follow;
use App\Entity\User;
use App\Manager\MailerManager;
use App\Repository\EventRepository;
use App\Repository\FollowRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tells a member of the events that went online since their last alert at the publishers and the venues they follow:
 * one e-mail a day at most, none without news. Until when they heard of them is kept on the member, so that a second
 * run, or a message retried, tells nothing twice.
 */
final readonly class FollowAlertSender
{
    /** The events an e-mail lists at most: the others are counted */
    public const int MAX_EVENTS = 12;

    public function __construct(
        private FollowRepository $followRepository,
        private EventRepository $eventRepository,
        private MailerManager $mailerManager,
        private FollowAlertUnsubscribeUrl $unsubscribeUrl,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param DateTimeImmutable $until the run's time: the events online by then
     *
     * @return bool whether an e-mail was sent: not to a member who turned them off or whose address it would not
     *              reach, nor without news
     */
    public function send(User $user, DateTimeImmutable $until): bool
    {
        if (!$user->isEnabled() || !$user->isFollowAlerts() || !$user->hasDeliverableEmail()) {
            return false;
        }

        $follows = $this->followRepository->findAllOfUser($user);
        if ([] === $follows) {
            return false;
        }

        $events = $this->findNews($user, $follows, $until);
        $user->setFollowAlertsSentAt($until);
        if ([] === $events) {
            $this->entityManager->flush();

            return false;
        }

        $this->mailerManager->sendFollowAlertEmail($user, \array_slice($events, 0, self::MAX_EVENTS), \count($events), $this->unsubscribeUrl->generate($user));
        $this->entityManager->flush();

        return true;
    }

    /**
     * The events that went online since the member last heard, and after they followed their publisher or venue.
     *
     * @param list<Follow> $follows
     *
     * @return list<Event>
     */
    private function findNews(User $user, array $follows, DateTimeImmutable $until): array
    {
        $followedSince = ['publisher' => [], 'place' => []];
        foreach ($follows as $follow) {
            $kind = null !== $follow->getPublisher() ? 'publisher' : 'place';
            $followedSince[$kind][(int) $follow->getTarget()->getId()] = $follow->getCreatedAt();
        }

        $oldest = min(array_map(static fn (Follow $follow): DateTimeImmutable => $follow->getCreatedAt() ?? $until, $follows));
        $since = max($user->getFollowAlertsSentAt() ?? $oldest, $oldest);

        $events = $this->eventRepository->findPublishedForFollowers(array_keys($followedSince['publisher']), array_keys($followedSince['place']), $since, $until);

        // An event published before the member followed its publisher or its venue is no news to them
        return array_values(array_filter($events, static function (Event $event) use ($followedSince): bool {
            foreach (['publisher' => $event->getPublisher(), 'place' => $event->getPlace()] as $kind => $target) {
                $followed = null !== $target ? ($followedSince[$kind][(int) $target->getId()] ?? null) : null;
                if (null !== $followed && $event->getPublishedAt() > $followed) {
                    return true;
                }
            }

            return false;
        }));
    }
}
