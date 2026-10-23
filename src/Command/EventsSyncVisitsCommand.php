<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Analytics\EventVisitSync;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Copies the event pages' visitors and ticketing clicks from Umami, shown to their authors in their personal space.
 * Scheduled on the host every hour: today and yesterday are read again, so yesterday's last hour is not lost.
 */
#[AsCommand('app:events:sync-visits', 'Copy the visitors and ticketing clicks of the event pages from Umami (hourly)')]
final class EventsSyncVisitsCommand extends Command
{
    public function __construct(
        private readonly EventVisitSync $eventVisitSync,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'How many days to read, today included', '2')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Read this day only (Y-m-d)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $date = $input->getOption('date');
        if (null !== $date) {
            $day = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date);
            if (false === $day) {
                $io->error(\sprintf('"%s" is not a Y-m-d date.', $date));

                return Command::INVALID;
            }
            $days = [$day];
        } else {
            $count = max(1, (int) $input->getOption('days'));
            $today = new DateTimeImmutable('today');
            $days = array_map(static fn (int $i): DateTimeImmutable => $today->modify("-$i days"), range($count - 1, 0));
        }

        $rows = [];
        foreach ($days as $day) {
            $written = $this->eventVisitSync->sync($day);
            $rows[] = [$day->format('Y-m-d'), $written['events'], $written['visitors'], $written['ticketClicks']];
        }

        $io->table(['Day', 'Events', 'Visitors', 'Ticketing clicks'], $rows);
        $io->success('Visits copied from Umami.');

        return Command::SUCCESS;
    }
}
