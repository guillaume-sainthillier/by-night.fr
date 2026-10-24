<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Outing\OutingChangeNotifier;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

/**
 * Tells the members going to events what changed in them since the last run (OutingChangeNotifier), a few times a day
 * (host cron). Run again, it sends nothing twice; two runs never overlap, or a member could hear of a change twice.
 */
#[AsCommand('app:outing-alerts:send', 'E-mail the members going to events whose dates, venue or status changed')]
final readonly class OutingAlertsSendCommand
{
    /** Longer than a run takes: the runs are hours apart */
    private const int LOCK_TTL = 3600;

    public function __construct(
        private OutingChangeNotifier $notifier,
        private LockFactory $lockFactory,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $lock = $this->lockFactory->createLock('app:outing-alerts:send', self::LOCK_TTL);
        if (!$lock->acquire()) {
            $io->note('Another run is telling the changes: this one stops.');

            return Command::SUCCESS;
        }

        try {
            $run = $this->notifier->sendFlagged($this->clock->now());
        } finally {
            $lock->release();
        }

        if ([] !== $run->getFailures()) {
            $io->warning(\sprintf('%d alert(s) could not be sent: the next run tries them again.', \count($run->getFailures())));
            $io->table(['Event', 'Member', 'E-mail', 'Error'], $run->getFailures());
        }

        $io->success(\sprintf('%d alert(s) sent.', $run->getSent()));

        return Command::SUCCESS;
    }
}
