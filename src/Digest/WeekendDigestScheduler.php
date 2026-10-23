<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Digest;

use App\Enum\DateRangePreset;
use App\Message\SendWeekendDigest;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues the weekend selection of every member who asked for it, one message each (WeekendDigestSender): the weekend
 * is the one of the day it runs, "Ce week-end" as the agenda reads it.
 */
final readonly class WeekendDigestScheduler
{
    public function __construct(
        private UserRepository $userRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return int the members queued: those not yet sent the selection of the week
     */
    public function schedule(DateTimeImmutable $today): int
    {
        $weekend = DateRangePreset::ThisWeekend->range($today);
        $ids = $this->userRepository->findWeekendDigestRecipientIds(WeekendDigestSender::weekOf($weekend));
        foreach ($ids as $id) {
            $this->messageBus->dispatch(new SendWeekendDigest($id, $weekend->from, $weekend->to));
        }

        return \count($ids);
    }
}
