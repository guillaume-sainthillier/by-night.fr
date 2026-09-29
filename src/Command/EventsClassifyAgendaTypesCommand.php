<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Elasticsearch\AgendaTypeClassifier;
use App\Stats\UpcomingEventCounter;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Finds the agenda type pages that list each event to come, which the counts of the agenda's type links read.
 * Scheduled on the host once a day, after the night's imports; the async worker re-indexes the events it changed.
 * The type counts of the cities and countries are recounted right after: the midnight app:events:count-upcoming
 * would only show the night's types the next day.
 */
#[AsCommand('app:events:classify-agenda-types', 'Store the agenda types of every event to come (daily)')]
final class EventsClassifyAgendaTypesCommand extends Command
{
    public function __construct(
        private readonly AgendaTypeClassifier $classifier,
        private readonly UpcomingEventCounter $counter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $written = $this->classifier->refresh(new DateTimeImmutable('today'));

        $zones = $written > 0 ? $this->counter->refreshAgendaTypes() : 0;

        $io->success(\sprintf('Agenda types of %d events updated, type counts of %d cities and countries.', $written, $zones));

        return Command::SUCCESS;
    }
}
