<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Stats\UpcomingEventCounter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Recounts the events to come that the home page, the portals and the footer read from places, cities and countries,
 * and the categories and agenda types of the events to come of each city and country.
 * Scheduled on the host just after midnight, when yesterday's events stop being "to come".
 */
#[AsCommand('app:events:count-upcoming', 'Recount the events to come of every place, city and country, and their categories and types (daily, just after midnight)')]
final class EventsCountUpcomingCommand extends Command
{
    public function __construct(
        private readonly UpcomingEventCounter $counter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $written = $this->counter->refresh();

        $io->table(['Countries', 'Cities', 'Places', 'Category counts', 'Type counts'], [[$written['countries'], $written['cities'], $written['places'], $written['categories'], $written['types']]]);
        $io->success('Counts of events to come updated.');

        return Command::SUCCESS;
    }
}
