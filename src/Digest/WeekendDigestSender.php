<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Digest;

use App\App\Location;
use App\Entity\User;
use App\Manager\MailerManager;
use App\Search\DateRange;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sends a member the weekend selection of their city, once a week at most: the week sent is kept on the member, so
 * that a second run of the week, or a message retried, sends nothing.
 */
final readonly class WeekendDigestSender
{
    /** Fewer events than that are not worth an e-mail */
    public const int MIN_EVENTS = 3;

    public function __construct(
        private WeekendDigestSelector $selector,
        private MailerManager $mailerManager,
        private WeekendDigestUnsubscribeUrl $unsubscribeUrl,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * The ISO week of a weekend ("2026-W43"), which a member is sent one selection of.
     */
    public static function weekOf(DateRange $weekend): string
    {
        return $weekend->from->format('o-\WW');
    }

    /**
     * @return bool whether the e-mail was sent: not to a member who gave it up, lost their city or got it already, nor
     *              to an address it would not reach, nor when their city has too little that weekend
     */
    public function send(User $user, DateRange $weekend): bool
    {
        $week = self::weekOf($weekend);
        $city = $user->getCity();
        if (!$user->isEnabled() || !$user->isWeekendDigest() || !$user->hasDeliverableEmail() || null === $city || $week === $user->getWeekendDigestSentWeek()) {
            return false;
        }

        $events = $this->selector->select($city, $weekend);
        if (\count($events) < self::MIN_EVENTS) {
            return false;
        }

        $this->mailerManager->sendWeekendDigestEmail($user, new Location()->setCity($city), $weekend, $events, $this->unsubscribeUrl->generate($user));
        $user->setWeekendDigestSentWeek($week);
        $this->entityManager->flush();

        return true;
    }
}
