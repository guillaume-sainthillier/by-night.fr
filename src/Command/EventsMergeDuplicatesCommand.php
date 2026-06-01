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
use DateTimeImmutable;
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

#[AsCommand('app:events:merge-duplicates', 'Find and merge duplicate events into a single event with multiple timesheets')]
final class EventsMergeDuplicatesCommand extends Command
{
    private const int BATCH_SIZE = 500;

    /**
     * Group events sharing an external_id base that only differs by a "-N" suffix
     * (e.g. "SP-123-0", "SP-123-1"). The canonical is the suffix-less base event.
     */
    private const string STRATEGY_SUFFIX = 'suffix';

    /**
     * Group events by content identity (origin + place + name) regardless of their
     * external_id. Needed for affiliate feeds such as Fnac Spectacles, where every
     * ticket product of the same show is imported with a distinct external_id.
     */
    private const string STRATEGY_CONTENT = 'content';

    private const array STRATEGIES = [self::STRATEGY_SUFFIX, self::STRATEGY_CONTENT];

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
            ->addOption('strategy', null, InputOption::VALUE_REQUIRED, \sprintf('How duplicates are grouped: "%s" or "%s"', self::STRATEGY_SUFFIX, self::STRATEGY_CONTENT), self::STRATEGY_SUFFIX)
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Batch size for processing', (string) self::BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $origin = $input->getOption('origin');
        $strategy = (string) $input->getOption('strategy');
        $batchSize = (int) $input->getOption('batch-size');

        if (!\in_array($strategy, self::STRATEGIES, true)) {
            $io->error(\sprintf('Unknown strategy "%s". Expected one of: %s.', $strategy, implode(', ', self::STRATEGIES)));

            return Command::INVALID;
        }

        if (self::STRATEGY_CONTENT === $strategy && null === $origin) {
            $io->warning('Content strategy groups events by place + name across the whole database. Restrict it with --origin (e.g. --origin=awin.fnac) unless you really mean to merge every origin.');
        }

        $io->section(\sprintf('Processing duplicate events (strategy: %s) using cursor pagination...', $strategy));

        $queryBuilder = $this->createDuplicateCandidatesQueryBuilder($origin, $strategy);
        $pagination = $this->createPagination($queryBuilder, $batchSize, $strategy);

        $mergedCount = 0;
        $processedGroups = 0;
        $currentGroup = [];
        $currentGroupKey = null;

        /** @var list<Event> $chunk */
        foreach ($pagination->getChunkResults() as $chunk) {
            foreach ($chunk as $event) {
                $groupKey = $this->getGroupKey($event, $strategy);

                // New group detected - process the previous group
                if (null !== $currentGroupKey && $groupKey !== $currentGroupKey) {
                    $mergedCount += $this->processGroup($currentGroup, $strategy, $currentGroupKey, $dryRun, $io);
                    ++$processedGroups;
                    $currentGroup = [];
                }

                $currentGroupKey = $groupKey;
                $currentGroup[] = $event;
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
        if (null !== $currentGroupKey && [] !== $currentGroup) {
            $mergedCount += $this->processGroup($currentGroup, $strategy, $currentGroupKey, $dryRun, $io);
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

    private function createDuplicateCandidatesQueryBuilder(?string $origin, string $strategy): QueryBuilder
    {
        $qb = $this->eventRepository->createQueryBuilder('e')
            ->where('e.duplicateOf IS NULL');

        if (self::STRATEGY_CONTENT === $strategy) {
            // Content identity needs a name and a resolved place to group on.
            $qb->andWhere('e.name IS NOT NULL')
                ->andWhere('e.placeExternalId IS NOT NULL');
        } else {
            $qb->andWhere('e.externalId IS NOT NULL')
                ->andWhere('REGEXP(e.externalId, :pattern) = true')
                ->setParameter('pattern', '-[0-9]+$');
        }

        if (null !== $origin) {
            $qb->andWhere('e.externalOrigin = :origin')
                ->setParameter('origin', $origin);
        }

        return $qb;
    }

    /**
     * @return CursorPagination<Event>
     */
    private function createPagination(QueryBuilder $queryBuilder, int $batchSize, string $strategy): CursorPagination
    {
        // The streaming group detection relies on members of a group being
        // contiguous, so order by exactly the fields that make up the group key.
        if (self::STRATEGY_CONTENT === $strategy) {
            $configurations = new OrderConfigurations(
                new OrderConfiguration('e.externalOrigin', static fn (Event $event): ?string => $event->getExternalOrigin()),
                new OrderConfiguration('e.placeExternalId', static fn (Event $event): ?string => $event->getPlaceExternalId()),
                new OrderConfiguration('e.name', static fn (Event $event): ?string => $event->getName()),
                new OrderConfiguration('e.id', static fn (Event $event): ?int => $event->getId()),
            );
        } else {
            $configurations = new OrderConfigurations(
                new OrderConfiguration('e.externalOrigin', static fn (Event $event): ?string => $event->getExternalOrigin()),
                new OrderConfiguration('e.externalId', static fn (Event $event): ?string => $event->getExternalId()),
                new OrderConfiguration('e.id', static fn (Event $event): ?int => $event->getId()),
            );
        }

        return new CursorPagination($queryBuilder, $configurations, $batchSize);
    }

    private function getGroupKey(Event $event, string $strategy): string
    {
        if (self::STRATEGY_CONTENT === $strategy) {
            return \sprintf('%s|%s|%s', $event->getExternalOrigin(), $event->getPlaceExternalId(), $event->getName());
        }

        $baseId = preg_replace('/-\d+$/', '', $event->getExternalId() ?? '');

        return \sprintf('%s|%s', $event->getExternalOrigin(), $baseId);
    }

    /**
     * @param list<Event> $events
     */
    private function processGroup(array $events, string $strategy, string $groupKey, bool $dryRun, SymfonyStyle $io): int
    {
        if ([] === $events) {
            return 0;
        }

        $canonical = $this->resolveCanonical($events, $strategy, $groupKey);
        if (null === $canonical) {
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
            if (null === $duplicate || null !== $duplicate->getDuplicateOf()) {
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

        // Keep the canonical's date range in sync with its (now merged) timesheets.
        if ($mergedCount > 0 && self::STRATEGY_CONTENT === $strategy) {
            $canonical = $this->eventRepository->find($canonicalId);
            if (null !== $canonical) {
                $this->realignDateRange($canonical);
            }
        }

        return $mergedCount;
    }

    /**
     * @param list<Event> $events
     */
    private function resolveCanonical(array $events, string $strategy, string $groupKey): ?Event
    {
        if (self::STRATEGY_CONTENT === $strategy) {
            return $this->selectCanonicalFromGroup($events);
        }

        return $this->findOrSetCanonical($events[0], $groupKey);
    }

    /**
     * Pick the surviving event for a content-grouped show. Prefer the parser's
     * stable content-hash event (it already carries the real timesheets); fall
     * back to the oldest event so existing public URLs keep resolving.
     *
     * @param list<Event> $events
     */
    private function selectCanonicalFromGroup(array $events): ?Event
    {
        $canonical = null;
        foreach ($events as $event) {
            if (!$this->isContentHashId($event->getExternalId())) {
                continue;
            }

            if (null === $canonical || $this->isOlder($event, $canonical)) {
                $canonical = $event;
            }
        }

        if (null !== $canonical) {
            return $canonical;
        }

        foreach ($events as $event) {
            if (null === $canonical || $this->isOlder($event, $canonical)) {
                $canonical = $event;
            }
        }

        return $canonical;
    }

    private function isOlder(Event $event, Event $reference): bool
    {
        return null !== $event->getId()
            && (null === $reference->getId() || $event->getId() < $reference->getId());
    }

    /**
     * A 40-char hex string is the content-hash external_id minted by the parsers
     * when they collapse duplicate rows, as opposed to a raw merchant product id.
     */
    private function isContentHashId(?string $externalId): bool
    {
        return null !== $externalId && 1 === preg_match('/^[0-9a-f]{40}$/', $externalId);
    }

    private function findOrSetCanonical(Event $event, string $groupKey): Event
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

    private function mergeTimesheets(Event $canonical, Event $duplicate): void
    {
        $canonical->batchUpdate = true;
        $duplicate->batchUpdate = true;

        // Get existing timesheet start/end pairs for deduplication
        $existingPairs = [];
        foreach ($canonical->getTimesheets() as $existing) {
            $existingPairs[$this->getTimesheetKey($existing->getStartAt(), $existing->getEndAt())] = true;
        }

        // Legacy canonical events (imported before the timesheet model, e.g. the
        // one-row-per-ticket Fnac feed) have a start/end date but no timesheet
        // rows: materialize their own date so it survives the merge.
        if ([] === $existingPairs && null !== $canonical->getStartDate()) {
            $this->addTimesheet(
                $canonical,
                $canonical->getStartDate(),
                $canonical->getEndDate() ?? $canonical->getStartDate(),
                $canonical->getHours(),
                $existingPairs,
            );
        }

        foreach ($this->getTimesheetTuples($duplicate) as [$startAt, $endAt, $hours]) {
            $key = $this->getTimesheetKey($startAt, $endAt);
            if (isset($existingPairs[$key])) {
                continue;
            }

            $this->addTimesheet($canonical, $startAt, $endAt, $hours, $existingPairs);
        }
    }

    /**
     * Collect an event's timesheets as [start, end, hours] tuples, synthesizing
     * one from its start/end date when it has none (legacy, pre-timesheet events).
     *
     * @return list<array{0: ?DateTimeImmutable, 1: ?DateTimeImmutable, 2: ?string}>
     */
    private function getTimesheetTuples(Event $event): array
    {
        $tuples = [];
        foreach ($event->getTimesheets() as $timesheet) {
            $tuples[] = [$timesheet->getStartAt(), $timesheet->getEndAt(), $timesheet->getHours()];
        }

        if ([] === $tuples && null !== $event->getStartDate()) {
            $tuples[] = [$event->getStartDate(), $event->getEndDate() ?? $event->getStartDate(), $event->getHours()];
        }

        return $tuples;
    }

    /**
     * @param array<string, true> $existingPairs
     */
    private function addTimesheet(Event $canonical, ?DateTimeImmutable $startAt, ?DateTimeImmutable $endAt, ?string $hours, array &$existingPairs): void
    {
        $timesheet = new EventTimesheet();
        $timesheet->setStartAt($startAt);
        $timesheet->setEndAt($endAt);
        $timesheet->setHours($hours);
        $canonical->addTimesheet($timesheet);

        $existingPairs[$this->getTimesheetKey($startAt, $endAt)] = true;
    }

    private function getTimesheetKey(?DateTimeImmutable $startAt, ?DateTimeImmutable $endAt): string
    {
        return \sprintf('%s|%s',
            $startAt?->format('Y-m-d H:i:s') ?? '',
            $endAt?->format('Y-m-d H:i:s') ?? ''
        );
    }

    /**
     * Widen the canonical's start/end date so it spans every merged timesheet.
     */
    private function realignDateRange(Event $canonical): void
    {
        $start = null;
        $end = null;
        foreach ($canonical->getTimesheets() as $timesheet) {
            $timesheetStart = $timesheet->getStartAt();
            $timesheetEnd = $timesheet->getEndAt() ?? $timesheetStart;

            if (null !== $timesheetStart && (null === $start || $timesheetStart < $start)) {
                $start = $timesheetStart;
            }

            if (null !== $timesheetEnd && (null === $end || $timesheetEnd > $end)) {
                $end = $timesheetEnd;
            }
        }

        if (null === $start) {
            return;
        }

        $canonical->batchUpdate = true;
        $canonical->setStartDate($start);
        $canonical->setEndDate($end ?? $start);
    }
}
