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
use App\Import\EventFamilyResolver;
use App\Repository\EventRepository;
use App\Utils\ObjectKey;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
use SortDirection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Settles the Fnac rows of the per-product scheme, the ones before #407 (2026-06-01).
 *
 * Their ids are merchant_product_ids, which the parser never publishes again, and the parser of the time read the
 * end of the ticket sale (valid_to, weeks after the show) as the end of the event: 4,467 of them still looked
 * upcoming on 2026-10-06, most for months after their only performance. app:events:retire-legacy-fnac-rows handed
 * the shows to their current row by identity hash, which misses the ones whose description changed since.
 *
 *  - A legacy canonical still upcoming with one current row at the same place under the same name redirects to it,
 *    with every row that pointed at it, and loses its identity hash: it stops lending its dates (1,712 shows with two
 *    pages). A run long over keeps its page: a show of the same name at the same place years later is another one.
 *  - A legacy row still upcoming has its sessions end the day they start, and its range follows: its start was the
 *    performance's date. The ones without a current row (3,176) keep their page with their real dates.
 *
 * Previews by default, writes with --apply.
 */
#[AsCommand('app:events:fix-legacy-fnac-rows', 'Redirect the pre-#407 Fnac rows to the row the feed still updates and end their sessions the day they start (preview by default, --apply to write)')]
final class EventsFixLegacyFnacRowsCommand extends Command
{
    private const string ORIGIN = 'awin.fnac';

    /**
     * Length of the sha1(name|place) external ids; the merchant_product_ids are digits.
     */
    private const int CURRENT_ID_LENGTH = 40;

    /**
     * Shows per flush when linking, events per flush when fixing dates.
     */
    private const int BATCH_SIZE = 200;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly EventFamilyResolver $familyResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the changes. Without it the command only prints what it would do');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');

        $today = new DateTimeImmutable('today');
        [$linked, $repointed, $ambiguous] = $this->linkToCurrentRows($io, $apply, $today);
        $fixed = $this->fixDates($io, $apply, $today);

        $summary = \sprintf(
            '%d legacy row(s) %s to their current row (with %d row(s) pointing at them), %d left alone: several current rows match. %d legacy row(s) %s their performance dates.',
            $linked,
            $apply ? 'redirected' : 'would be redirected',
            $repointed,
            $ambiguous,
            $fixed,
            $apply ? 'given' : 'would be given',
        );

        if ($apply) {
            $io->success($summary);
        } else {
            $io->note($summary . ' Preview only, nothing was written: re-run with --apply.');
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{int, int, int} the legacy rows redirected, the rows re-pointed with them, the ambiguous ones
     */
    private function linkToCurrentRows(SymfonyStyle $io, bool $apply, DateTimeImmutable $today): array
    {
        [$successors, $ambiguous] = $this->findSuccessors($today);
        $io->section(\sprintf('%s %d legacy canonical(s) with a current row', $apply ? 'Linking' : 'Previewing', \count($successors)));

        $linked = 0;
        $repointed = 0;
        $sample = [];
        $io->progressStart(\count($successors));
        foreach (array_chunk($successors, self::BATCH_SIZE, true) as $chunk) {
            $events = [];
            foreach ($this->eventRepository->findBy(['id' => [...array_keys($chunk), ...array_values($chunk)]]) as $event) {
                $events[(int) $event->getId()] = $event;
            }

            /** @var array<int, list<Event>> $pointing the rows pointing at each legacy canonical */
            $pointing = [];
            foreach ($this->eventRepository->findBy(['duplicateOf' => array_keys($chunk)]) as $row) {
                $pointing[(int) $row->getDuplicateOf()?->getId()][] = $row;
            }

            foreach ($chunk as $legacyId => $currentId) {
                $legacy = $events[$legacyId];
                $current = $events[$currentId];

                if (\count($sample) < 10) {
                    $sample[] = [$legacy->getName(), $legacyId, $currentId];
                }

                // The current row may have been a duplicate of the legacy one: it heads the show now
                $current->setDuplicateOf(null);
                $legacy->setDuplicateOf($current);
                $legacy->setIdentityHash(null);
                ++$linked;

                // No redirect to a redirect: the rows pointing at the legacy canonical follow it
                foreach ($pointing[$legacyId] ?? [] as $row) {
                    if ($row === $current) {
                        continue;
                    }

                    $row->setDuplicateOf($current);
                    if (!self::isCurrent($row)) {
                        $row->setIdentityHash(null);
                    }

                    ++$repointed;
                }
            }

            if ($apply) {
                $this->entityManager->flush();
                $this->entityManager->clear();

                // Drops what the former canonicals inherited and realigns the current rows on their own dates
                $this->familyResolver->resolveForEvents(array_values($chunk));
            }

            $this->entityManager->clear();
            $io->progressAdvance(\count($chunk));
        }

        $io->progressFinish();
        $io->table(['Show', 'Legacy row', 'Current row'], $sample);

        return [$linked, $repointed, $ambiguous];
    }

    /**
     * The current row of each legacy canonical still upcoming (by its stored range, the sale's end): at the same place under the same name (compared by the column's
     * collation, like the import's lookups), canonical or pointing at the legacy row. A legacy row matching several
     * current rows (addresses spelled differently, merged into one place) is left alone.
     *
     * @return array{array<int, int>, int} the current row id by legacy row id, the number of ambiguous legacy rows
     */
    private function findSuccessors(DateTimeImmutable $today): array
    {
        /** @var list<array{legacyId: int|string, currentId: int|string}> $rows */
        $rows = $this->eventRepository
            ->createQueryBuilder('e')
            ->select('e.id AS legacyId, s.id AS currentId')
            ->join(Event::class, 's', 'WITH', 's.externalOrigin = e.externalOrigin AND LENGTH(s.externalId) = :length AND s.place = e.place AND s.name = e.name AND (s.duplicateOf IS NULL OR s.duplicateOf = e)')
            ->where('e.externalOrigin = :origin')
            ->andWhere('LENGTH(e.externalId) <> :length')
            ->andWhere('e.duplicateOf IS NULL')
            ->andWhere('e.endDate >= :today')
            ->orderBy('e.id', SortDirection::Ascending)
            ->setParameter('origin', self::ORIGIN)
            ->setParameter('length', self::CURRENT_ID_LENGTH)
            ->setParameter('today', $today)
            ->getQuery()
            ->getArrayResult();

        $candidates = [];
        foreach ($rows as $row) {
            $candidates[(int) $row['legacyId']][] = (int) $row['currentId'];
        }

        $successors = [];
        foreach ($candidates as $legacyId => $currentIds) {
            if (1 === \count($currentIds)) {
                $successors[$legacyId] = $currentIds[0];
            }
        }

        return [$successors, \count($candidates) - \count($successors)];
    }

    /**
     * End every session of the legacy rows still upcoming on the day it starts, and their range with their last one.
     */
    private function fixDates(SymfonyStyle $io, bool $apply, DateTimeImmutable $today): int
    {
        // Walked by id, from the last event of the previous chunk: a fixed event leaves the query
        $pagination = new CursorPagination(
            $this->eventRepository
                ->createQueryBuilder('e')
                ->where('e.externalOrigin = :origin')
                ->andWhere('LENGTH(e.externalId) <> :length')
                ->andWhere(\sprintf('e.endDate >= :today OR EXISTS (%s)', $this->timesheetsQuery('t1', 't1.endAt >= :today')))
                ->andWhere(\sprintf('e.endDate <> e.startDate OR EXISTS (%s)', $this->timesheetsQuery('t2', 't2.endAt <> t2.startAt')))
                ->setParameter('origin', self::ORIGIN)
                ->setParameter('length', self::CURRENT_ID_LENGTH)
                ->setParameter('today', $today),
            new OrderConfigurations(new OrderConfiguration('e.id', static fn (Event $event): ?int => $event->getId())),
            self::BATCH_SIZE,
            fetchJoinCollection: false,
        );
        $total = \count($pagination);
        $io->section(\sprintf('%s %d legacy row(s) spanning more than their performances', $apply ? 'Fixing' : 'Previewing', $total));

        $fixed = 0;
        $sample = [];
        $io->progressStart($total);
        foreach ($pagination->getChunkResults() as $events) {
            $ids = [];
            foreach ($events as $event) {
                $stored = \sprintf('%s → %s', $event->getStartDate()?->format('Y-m-d'), $event->getEndDate()?->format('Y-m-d'));
                if (!$this->endSessionsTheDayTheyStart($event)) {
                    continue;
                }

                if (\count($sample) < 10) {
                    $sample[] = [$event->getId(), $event->getName(), $stored, \sprintf('%s → %s', $event->getStartDate()?->format('Y-m-d'), $event->getEndDate()?->format('Y-m-d'))];
                }

                $ids[] = (int) $event->getId();
                ++$fixed;
            }

            if ($apply) {
                $this->entityManager->flush();
                $this->entityManager->clear();

                // The canonicals these rows lend their sessions to copy the fixed ones
                $this->familyResolver->resolveForEvents($ids);
            }

            $this->entityManager->clear();
            $io->progressAdvance(\count($events));
        }

        $io->progressFinish();
        $io->table(['Id', 'Event', 'Stored', 'Fixed'], $sample);

        return $fixed;
    }

    /**
     * A Fnac session is one performance: it ends the day it starts, inherited copies included (the resolver rebuilds
     * them the same). Sessions that become the same are kept once.
     *
     * @return bool whether anything changed
     */
    private function endSessionsTheDayTheyStart(Event $event): bool
    {
        $changed = false;
        $kept = [];
        $days = [];
        foreach ($event->getTimesheets()->toArray() as $timesheet) {
            $startAt = $timesheet->getStartAt();
            if (null === $startAt) {
                continue;
            }

            if (self::day($timesheet->getEndAt()) !== self::day($startAt)) {
                $timesheet->setEndAt($startAt);
                $changed = true;
            }

            $key = \sprintf('%s %s', $timesheet->getSourceEvent()?->getId(), ObjectKey::timesheet($startAt, $startAt, $timesheet->getStartTime(), $timesheet->getEndTime(), $timesheet->getHours()));
            if (isset($kept[$key])) {
                $event->removeTimesheet($timesheet);

                continue;
            }

            $kept[$key] = true;
            $days[self::day($startAt)] = $startAt;
        }

        ksort($days);
        $start = [] === $days ? $event->getStartDate() : $days[array_key_first($days)];
        $end = [] === $days ? $start : $days[array_key_last($days)];
        if (self::day($event->getStartDate()) !== self::day($start) || self::day($event->getEndDate()) !== self::day($end)) {
            $event->setStartDate($start);
            $event->setEndDate($end);
            $changed = true;
        }

        // Sessions are indexed and the search listener only watches the event row
        if ($changed) {
            $event->setUpdatedAt(new DateTimeImmutable());
        }

        return $changed;
    }

    private function timesheetsQuery(string $alias, string $condition): string
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select($alias . '.id')
            ->from(EventTimesheet::class, $alias)
            ->where(\sprintf('%s.event = e', $alias))
            ->andWhere($condition)
            ->getDQL();
    }

    private static function day(?DateTimeImmutable $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    private static function isCurrent(Event $event): bool
    {
        return self::CURRENT_ID_LENGTH === \strlen((string) $event->getExternalId());
    }
}
