<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Follow\FollowAlertScheduler;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The new events of the publishers and the venues the members follow, once a day (host cron). Run again the same
 * day, it sends nothing more.
 */
#[AsCommand('app:follows:send-alerts', 'Queue the e-mails telling the members of the new events they follow')]
final readonly class FollowAlertsSendCommand
{
    public function __construct(
        private FollowAlertScheduler $scheduler,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $count = $this->scheduler->schedule($this->clock->now());
        $io->success(\sprintf('%d member(s) queued.', $count));

        return Command::SUCCESS;
    }
}
