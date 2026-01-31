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
use App\Entity\EventTimesheet;
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

#[AsCommand('app:events:merge-duplicates', 'Find and merge duplicate events based on external_id suffix')]
final class EventsMergeDuplicatesCommand extends Command
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without saving')
            ->addOption('origin', null, InputOption::VALUE_REQUIRED, 'Only process events from this external origin')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Batch size for processing', (string) self::BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $origin = $input->getOption('origin');
        $batchSize = (int) $input->getOption('batch-size');

        $io->section('Processing duplicate events using cursor pagination...');

        $queryBuilder = $this->createDuplicateCandidatesQueryBuilder($origin);
        $pagination = $this->createPagination($queryBuilder, $batchSize);

        $mergedCount = 0;
        $processedGroups = 0;
        $currentGroup = null;
        $currentGroupKey = null;
        $canonical = null;

        /** @var list<Event> $chunk */
        foreach ($pagination->getChunkResults() as $chunk) {
            foreach ($chunk as $event) {
                $groupKey = $this->getGroupKey($event);

                // New group detected - process the previous group
                if (null !== $currentGroupKey && $groupKey !== $currentGroupKey) {
                    $mergedCount += $this->processGroup($currentGroup, $canonical, $dryRun, $io);
                    ++$processedGroups;
                    $currentGroup = [];
                    $canonical = null;
                }

                $currentGroupKey = $groupKey;
                $currentGroup[] = $event;

                // Determine canonical event for this group
                if (null === $canonical) {
                    $canonical = $this->findOrSetCanonical($event, $origin, $groupKey);
                }
            }

            // Flush and clear after each chunk
            if (!$dryRun) {
                $this->entityManager->flush();
            }
            $this->entityManager->clear();
            gc_collect_cycles();

            $io->writeln(\sprintf('  Processed %d groups, merged %d events so far...', $processedGroups, $mergedCount));
        }

        // Process the last group
        if (null !== $currentGroupKey && null !== $currentGroup) {
            $mergedCount += $this->processGroup($currentGroup, $canonical, $dryRun, $io);
            ++$processedGroups;

            if (!$dryRun) {
                $this->entityManager->flush();
            }
        }

        $io->newLine();

        if (!$dryRun) {
            $io->success(\sprintf('Merged %d duplicate events across %d groups', $mergedCount, $processedGroups));
        } else {
            $io->info(\sprintf('Would merge %d duplicate events across %d groups (dry-run)', $mergedCount, $processedGroups));
        }

        return Command::SUCCESS;
    }

    private function createDuplicateCandidatesQueryBuilder(?string $origin): QueryBuilder
    {
        $qb = $this->eventRepository->createQueryBuilder('e')
            ->where('e.duplicateOf IS NULL')
            ->andWhere('e.externalId IS NOT NULL')
            ->andWhere('REGEXP(e.externalId, :pattern) = true')
            ->setParameter('pattern', '-[0-9]+$');

        if (null !== $origin) {
            $qb->andWhere('e.externalOrigin = :origin')
                ->setParameter('origin', $origin);
        }

        return $qb;
    }

    /**
     * @return CursorPagination<Event>
     */
    private function createPagination(QueryBuilder $queryBuilder, int $batchSize): CursorPagination
    {
        $configurations = new OrderConfigurations(
            new OrderConfiguration('e.externalOrigin', static fn (Event $event): ?string => $event->getExternalOrigin()),
            new OrderConfiguration('e.externalId', static fn (Event $event): ?string => $event->getExternalId()),
            new OrderConfiguration('e.id', static fn (Event $event): ?int => $event->getId()),
        );

        return new CursorPagination($queryBuilder, $configurations, $batchSize);
    }

    private function getGroupKey(Event $event): string
    {
        $baseId = preg_replace('/-\d+$/', '', $event->getExternalId() ?? '');

        return \sprintf('%s|%s', $event->getExternalOrigin(), $baseId);
    }

    private function findOrSetCanonical(Event $event, ?string $origin, string $groupKey): Event
    {
        [$externalOrigin, $baseId] = explode('|', $groupKey);

        // Try to find existing canonical (no suffix)
        $canonical = $this->eventRepository->findOneBy([
            'externalId' => $baseId,
            'externalOrigin' => $externalOrigin,
            'duplicateOf' => null,
        ]);

        // Try with -0 suffix
        if (null === $canonical) {
            $canonical = $this->eventRepository->findOneBy([
                'externalId' => $baseId . '-0',
                'externalOrigin' => $externalOrigin,
                'duplicateOf' => null,
            ]);
        }

        // If no canonical found, use the current event (first in sorted order)
        return $canonical ?? $event;
    }

    /**
     * @param list<Event> $events
     */
    private function processGroup(array $events, ?Event $canonical, bool $dryRun, SymfonyStyle $io): int
    {
        if (null === $canonical || [] === $events) {
            return 0;
        }

        $mergedCount = 0;
        $canonicalId = $canonical->getId();

        foreach ($events as $event) {
            if ($event->getId() === $canonicalId) {
                continue;
            }

            // Re-fetch canonical if it was cleared from EntityManager
            $canonical = $this->eventRepository->find($canonicalId);
            if (null === $canonical) {
                continue;
            }

            // Re-fetch duplicate event if cleared
            $duplicate = $this->eventRepository->find($event->getId());
            if (null === $duplicate) {
                continue;
            }

            $this->mergeTimesheets($canonical, $duplicate);
            $duplicate->setDuplicateOf($canonical);
            ++$mergedCount;

            if ($io->isVerbose()) {
                $io->writeln(\sprintf(
                    '  [%s] Event #%d (ext: %s) -> #%d (ext: %s)',
                    $dryRun ? 'DRY-RUN' : 'MERGE',
                    $duplicate->getId(),
                    $duplicate->getExternalId(),
                    $canonical->getId(),
                    $canonical->getExternalId()
                ));
            }
        }

        return $mergedCount;
    }

    private function mergeTimesheets(Event $canonical, Event $duplicate): void
    {
        // Get existing timesheet start/end pairs for deduplication
        $existingPairs = [];
        foreach ($canonical->getTimesheets() as $existing) {
            $key = \sprintf('%s|%s',
                $existing->getStartAt()?->format('Y-m-d H:i:s') ?? '',
                $existing->getEndAt()?->format('Y-m-d H:i:s') ?? ''
            );
            $existingPairs[$key] = true;
        }

        foreach ($duplicate->getTimesheets() as $timesheet) {
            $key = \sprintf('%s|%s',
                $timesheet->getStartAt()?->format('Y-m-d H:i:s') ?? '',
                $timesheet->getEndAt()?->format('Y-m-d H:i:s') ?? ''
            );

            if (!isset($existingPairs[$key])) {
                $newTimesheet = new EventTimesheet();
                $newTimesheet->setStartAt($timesheet->getStartAt());
                $newTimesheet->setEndAt($timesheet->getEndAt());
                $newTimesheet->setHours($timesheet->getHours());
                $canonical->addTimesheet($newTimesheet);
                $existingPairs[$key] = true;
            }
        }
    }
}
