<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Elasticsearch\EventIndexableChecker;
use App\Entity\Event;
use App\Enum\EventStatus;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand('app:events:migrate-status', 'Migrate status_message values to EventStatus enum')]
final class EventsStatusMigrateCommand extends Command
{
    private const int BATCH_SIZE = 10000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly EventIndexableChecker $eventIndexableChecker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without saving')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Batch size for processing', (string) self::BATCH_SIZE)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force re-migration of already migrated events');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->eventIndexableChecker->setEnabled(false);
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');
        $force = $input->getOption('force');

        $io->section('Migrating status_message values to EventStatus enum...');

        $queryBuilder = $this->createEventsQueryBuilder($force);
        $pagination = $this->createPagination($queryBuilder, $batchSize);

        $migratedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        /** @var list<Event> $chunk */
        foreach ($pagination->getChunkResults() as $chunk) {
            foreach ($chunk as $event) {
                try {
                    $migrated = $this->migrateEvent($event, $dryRun, $io);
                    if ($migrated) {
                        ++$migratedCount;
                    } else {
                        ++$skippedCount;
                    }
                } catch (Throwable $e) {
                    ++$errorCount;
                    if ($io->isVerbose()) {
                        $io->warning(\sprintf('Error migrating event #%d: %s', $event->getId(), $e->getMessage()));
                    }
                }
            }

            // Flush and clear after each chunk
            if (!$dryRun) {
                $this->entityManager->flush();
            }
            $this->entityManager->clear();
            gc_collect_cycles();

            $io->writeln(\sprintf('  Migrated: %d, Skipped: %d, Errors: %d', $migratedCount, $skippedCount, $errorCount));
        }

        $io->newLine();

        if (!$dryRun) {
            $io->success(\sprintf('Migrated %d events, skipped %d, errors %d', $migratedCount, $skippedCount, $errorCount));
        } else {
            $io->info(\sprintf('Would migrate %d events, skip %d, errors %d (dry-run)', $migratedCount, $skippedCount, $errorCount));
        }

        return Command::SUCCESS;
    }

    private function createEventsQueryBuilder(bool $force): QueryBuilder
    {
        $qb = $this->eventRepository
            ->createQueryBuilder('e')
            ->where('e.statusMessage IS NOT NULL AND e.statusMessage != :empty')
            ->setParameter('empty', '');

        if (!$force) {
            // Only process events that haven't been migrated yet
            $qb->andWhere('e.status IS NULL');
        }

        return $qb;
    }

    /**
     * @return CursorPagination<Event>
     */
    private function createPagination(QueryBuilder $queryBuilder, int $batchSize): CursorPagination
    {
        $configurations = new OrderConfigurations(
            new OrderConfiguration('e.id', static fn (Event $event): ?int => $event->getId()),
        );

        return new CursorPagination($queryBuilder, $configurations, $batchSize);
    }

    private function migrateEvent(Event $event, bool $dryRun, SymfonyStyle $io): bool
    {
        $event->batchUpdate = true;
        $statusMessage = $event->getStatusMessage();

        if (null === $statusMessage || '' === $statusMessage) {
            return false;
        }

        $status = $this->mapStatusMessageToEnum($statusMessage);
        $event->setStatus($status);
        if (null !== $status) {
            // Clear status message only if we could map it
            $event->setStatusMessage(null);
        }

        if ($io->isVeryVerbose()) {
            $statusLabel = $status?->getLabel() ?? 'null';
            $io->writeln(\sprintf(
                '  [%s] Event #%d: status_message="%s" -> status=%s',
                $dryRun ? 'DRY-RUN' : 'MIGRATE',
                $event->getId(),
                $statusMessage,
                $statusLabel
            ));
        }

        return true;
    }

    public function mapStatusMessageToEnum(?string $statusMessage): ?EventStatus
    {
        return EventStatus::fromStatusMessage($statusMessage);
    }
}
