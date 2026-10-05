<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Handler\EventImageDownloader;
use App\Repository\EventRepository;
use App\Utils\Monitor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:events:download-images',
    description: 'Download all missing event images',
)]
final class EventsDownloadImagesCommand extends Command
{
    private const int DEFAULT_BATCH_SIZE = 50;

    public function __construct(
        private readonly EventImageDownloader $eventImageDownloader,
        private readonly EventRepository $eventRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Events downloaded per flush', (string) self::DEFAULT_BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Handling event image download');
        $batchSize = max(1, (int) $input->getOption('batch-size'));

        Monitor::createProgressBar((int) ceil($this->eventRepository->countWaitingForImage() / $batchSize));
        foreach ($this->eventImageDownloader->downloadWaiting($batchSize) as $downloaded) {
            Monitor::advanceProgressBar();
        }

        Monitor::finishProgressBar();

        return Command::SUCCESS;
    }
}
