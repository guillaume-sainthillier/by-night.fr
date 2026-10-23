<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Digest\WeekendDigestSender;
use App\Message\SendWeekendDigest;
use App\Repository\UserRepository;
use App\Search\DateRange;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendWeekendDigestHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private WeekendDigestSender $sender,
    ) {
    }

    public function __invoke(SendWeekendDigest $message): void
    {
        // A weekend over is no news anymore
        $user = $this->userRepository->find($message->userId);
        if (null === $user || $message->to < new DateTimeImmutable('today')) {
            return;
        }

        $this->sender->send($user, new DateRange($message->from, $message->to));
    }
}
