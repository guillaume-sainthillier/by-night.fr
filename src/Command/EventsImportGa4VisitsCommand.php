<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Analytics\EventVisitBackfill;
use App\Analytics\GoogleAnalyticsClient;
use App\Repository\EventVisitStatRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * One-shot, to remove once run in production: fills the days before Umami's first one with the event pages' visitors
 * from Google Analytics (EventVisitBackfill), so organisers do not start from an empty chart. Run after the first
 * `app:events:sync-visits`, with the JSON key of a service account given Viewer on the GA4 property.
 */
#[AsCommand('app:events:import-ga4-visits', 'Fill the days before Umami with the event pages\' visitors from Google Analytics (one-shot)')]
final class EventsImportGa4VisitsCommand extends Command
{
    /** "by-night.fr - GA4" */
    private const string PROPERTY_ID = '316503959';

    public function __construct(
        private readonly EventVisitBackfill $eventVisitBackfill,
        private readonly EventVisitStatRepository $eventVisitStatRepository,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('key-file', null, InputOption::VALUE_REQUIRED, 'The JSON key of a service account given Viewer on the GA4 property')
            ->addOption('property', null, InputOption::VALUE_REQUIRED, 'The GA4 property id', self::PROPERTY_ID)
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'How many days to fill, ending the day before Umami\'s first', '90')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Read Google Analytics, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $keyFile = (string) $input->getOption('key-file');
        if ('' === $keyFile) {
            $io->error('--key-file is required.');

            return Command::INVALID;
        }

        $firstUmamiDay = $this->eventVisitStatRepository->findFirstUmamiDay();
        if (null === $firstUmamiDay) {
            $io->error('Nothing was read from Umami yet: run app:events:sync-visits first, the backfill stops the day before.');

            return Command::FAILURE;
        }

        $client = GoogleAnalyticsClient::fromKeyFile($this->httpClient, $keyFile, (string) $input->getOption('property'));
        $dryRun = (bool) $input->getOption('dry-run');
        $count = max(1, (int) $input->getOption('days'));

        $rows = [];
        foreach (range($count, 1) as $i) {
            $day = $firstUmamiDay->modify("-$i days");
            $read = $this->eventVisitBackfill->import($client, $day, $dryRun);
            $rows[] = [$day->format('Y-m-d'), $read['events'], $read['visitors'], $read['unattributed']];
        }

        $io->table(['Day', 'Events', 'Visitors', 'Visitors in "(other)"'], $rows);
        $io->success($dryRun ? 'Read from Google Analytics, nothing written (--dry-run).' : 'Days before Umami filled from Google Analytics.');

        return Command::SUCCESS;
    }
}
