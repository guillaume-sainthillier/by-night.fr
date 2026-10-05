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
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Finds the agenda type pages that list each event to come, which the type pages and their counts read. Each event is
 * already classified once indexed (ClassifyEventsHandler): run it to fill the types of the events indexed before, or
 * after a change of the type terms (AgendaType::getTerms()); the async worker re-indexes the events it changed.
 */
#[AsCommand('app:events:classify-agenda-types', 'Store the agenda types of every event to come (a backfill: the indexing classifies each event)')]
final class EventsClassifyAgendaTypesCommand extends Command
{
    public function __construct(
        private readonly AgendaTypeClassifier $classifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $written = $this->classifier->refresh(new DateTimeImmutable('today'));

        $io->success(\sprintf('Agenda types of %d events updated.', $written));

        return Command::SUCCESS;
    }
}
