<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Entity\Event;
use App\Handler\EventHandler;
use App\Repository\EventRepository;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly EventHandler $eventHandler,
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

        $qb = $this
            ->eventRepository
            ->createQueryBuilder('e')
            ->where('e.url IS NOT NULL')
            ->andWhere("e.imageSystem.name IS NULL OR e.imageSystem.name = ''")
            // Taken down on request (EventImageRemover): not to be downloaded again
            ->andWhere('e.imageRemovedAt IS NULL')
        ;

        // Keyset pagination on the id: a downloaded image takes its event out of the filter, so
        // page numbers moved the offset past as many events as the page before had fixed, and
        // about half of the backlog was never attempted.
        $configurations = new OrderConfigurations(
            new OrderConfiguration('e.id', static fn (Event $event): ?int => $event->getId(), orderAscending: false),
        );
        /** @var CursorPagination<Event> $pagination */
        $pagination = new CursorPagination($qb, $configurations, $batchSize, fetchJoinCollection: false);

        Monitor::createProgressBar((int) ceil(\count($pagination) / $batchSize));
        foreach ($pagination->getChunkResults() as $events) {
            // Images are not part of the indexed document: flag the events so the
            // FOS Elastica listener skips re-indexing them (ConditionalUpdate).
            foreach ($events as $event) {
                $event->batchUpdate = true;
            }

            try {
                $this->eventHandler->handleDownloads($events);

                $this->entityManager->flush();
                $this->entityManager->clear();
            } finally {
                $this->eventHandler->reset();
            }

            Monitor::advanceProgressBar();
        }

        Monitor::finishProgressBar();

        return Command::SUCCESS;
    }
}
