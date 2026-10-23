<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Digest\WeekendDigestScheduler;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The weekend selection of the members who asked for it, each Thursday morning (host cron). Run again the same week,
 * it sends only to the members not sent it yet.
 */
#[AsCommand('app:digest:send', 'Queue the weekend selection of every member who asked for it')]
final readonly class DigestSendCommand
{
    public function __construct(
        private WeekendDigestScheduler $scheduler,
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
