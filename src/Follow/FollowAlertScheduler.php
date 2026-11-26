<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Follow;

use App\Message\SendFollowAlert;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues the follow alerts of the day, one message per member following a publisher or a venue (FollowAlertSender).
 */
final readonly class FollowAlertScheduler
{
    public function __construct(
        private UserRepository $userRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return int the members queued: those who have not heard of their news today
     */
    public function schedule(DateTimeImmutable $now): int
    {
        $ids = $this->userRepository->findFollowAlertRecipientIds($now);
        foreach ($ids as $id) {
            $this->messageBus->dispatch(new SendFollowAlert($id, $now));
        }

        return \count($ids);
    }
}
