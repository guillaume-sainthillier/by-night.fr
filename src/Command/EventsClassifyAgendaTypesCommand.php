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
 * Finds the agenda type pages that list each event to come, which the counts of the agenda's type links read, then
 * recounts the type counts of every city and country. Scheduled on the host after the parsers: the indexing already
 * classified each event it wrote (ClassifyEventsHandler), but only recounts those changed on the site, so the type
 * cards count the night's imports from here. It also fills the types of the events indexed before a change of the
 * type terms (AgendaType::getTerms()); the async worker re-indexes the events it changed.
 */
#[AsCommand('app:events:classify-agenda-types', 'Store the agenda types of every event to come and recount the type counts (after the parsers)')]
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

        // Even when nothing changed here: the indexing classified the imports, without recounting them
        $counts = $this->counter->refreshAgendaTypes();

        $io->success(\sprintf('Agenda types of %d events updated, %d type counts of cities and countries stored.', $written, $counts));

        return Command::SUCCESS;
    }
}
