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
use App\Repository\EventRepository;
use App\Service\TagConverter;
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

#[AsCommand('app:events:migrate-tags', 'Migrate category/theme strings to Tag entities')]
final class EventsTagMigrateCommand extends Command
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly TagConverter $tagConverter,
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
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');
        $force = $input->getOption('force');

        $io->section('Migrating category/theme strings to Tag entities...');

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
        $qb = $this->eventRepository->createQueryBuilder('e')
            ->where('e.category IS NOT NULL OR e.theme IS NOT NULL');

        if (!$force) {
            // Only process events that haven't been migrated yet
            $qb->andWhere('e.categoryTag IS NULL')
                ->andWhere('SIZE(e.themeTags) = 0');
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
        $category = $event->getCategory();
        $theme = $event->getTheme();

        // Skip if nothing to migrate
        if ((null === $category || '' === $category) && (null === $theme || '' === $theme)) {
            return false;
        }

        // Convert strings to Tag entities
        $result = $this->tagConverter->convert($category, $theme);

        // Set the category tag
        if (null !== $result['category']) {
            $event->setCategoryTag($result['category']);
        }

        // Set the theme tags
        $event->clearThemeTags();
        foreach ($result['themes'] as $themeTag) {
            $event->addThemeTag($themeTag);
        }

        if ($io->isVeryVerbose()) {
            $categoryName = $result['category']?->getName() ?? 'null';
            $themeNames = array_map(static fn ($t) => $t->getName(), $result['themes']);
            $io->writeln(\sprintf(
                '  [%s] Event #%d: category="%s" -> Tag[%s], theme="%s" -> Tags[%s]',
                $dryRun ? 'DRY-RUN' : 'MIGRATE',
                $event->getId(),
                $category ?? '',
                $categoryName,
                $theme ?? '',
                implode(', ', $themeNames)
            ));
        }

        return true;
    }
}
