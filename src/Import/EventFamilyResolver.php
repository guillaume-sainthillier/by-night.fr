<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import;

use App\Entity\Event;
use App\Entity\EventTimesheet;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Groups the rows that describe the same event under distinct external ids into a
 * family, and materializes the family on one of them.
 *
 * Some sources publish one record per session of an event: OpenAgenda organizers
 * duplicate an event for each date instead of adding a timing to it. Every record
 * keeps its own row, external id, exploration and timesheets, so the import keeps
 * tracking each source record on its own. Rows sharing an identity hash (see
 * EventContentHasher::identity()) form a family:
 *  - one member is the canonical event, the others point at it through duplicateOf
 *    (301 redirect, excluded from the search index and the listings);
 *  - the canonical carries the union of the family's dates: its own timesheets plus
 *    an inherited copy of each sibling's own timesheets, tagged with the sibling it
 *    comes from, so the copy is rebuilt on every change and goes away with the
 *    sibling (ON DELETE CASCADE);
 *  - its start and end dates span that union, since the listings filter and sort
 *    on them.
 *
 * Links that predate the identity hash (app:events:merge-duplicates) are left as
 * they are: a row without a hash is neither moved nor asked to lend its dates.
 */
final readonly class EventFamilyResolver
{
    public function __construct(
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Resolve the families of the given events, just imported: the ones they now
     * belong to and the ones they may have left. Runs in the import transaction,
     * after the batch has been merged and the EntityManager cleared.
     *
     * @param int[] $eventIds
     */
    public function resolveForEvents(array $eventIds): void
    {
        $eventIds = array_values(array_unique(array_filter($eventIds)));
        if ([] === $eventIds) {
            return;
        }

        $candidates = $this->eventRepository->findFamilyCandidates($eventIds);

        $hashes = [];
        foreach ($candidates as $candidate) {
            if (null !== $candidate->getIdentityHash()) {
                $hashes[$candidate->getIdentityHash()] = true;
            }
        }

        $familyHashes = $this->eventRepository->findSharedIdentityHashes(array_keys($hashes));

        /** @var array<int, true> $dirty canonical ids whose materialization must be rebuilt */
        $dirty = [];

        foreach ($candidates as $candidate) {
            $canonical = $candidate->getDuplicateOf();
            if (null === $canonical) {
                continue;
            }

            // A linked row was touched: either the duplicate's own dates changed, or
            // the canonical's range was reset by the import. Rebuild either way.
            $dirty[(int) $canonical->getId()] = true;

            // The row demonstrably describes another event than its canonical now: set
            // it free. If it has a family of its own, wireFamilies() re-links it below.
            $hash = $candidate->getIdentityHash();
            if (null !== $hash && null !== $canonical->getIdentityHash() && $hash !== $canonical->getIdentityHash()) {
                $candidate->setDuplicateOf(null);
                $dirty[(int) $candidate->getId()] = true;
            }
        }

        $this->wireFamilies($familyHashes, $dirty);
        $this->entityManager->flush();

        $this->materializeAll(array_keys($dirty));
        $this->entityManager->flush();

        if ([] !== $familyHashes || [] !== $dirty) {
            $this->logger->info('Resolved {families} event families, rebuilt {canonicals} canonical(s)', [
                'families' => \count($familyHashes),
                'canonicals' => \count($dirty),
            ]);
        }
    }

    /**
     * Resolve the given families from scratch (backfill).
     *
     * @param string[] $hashes identity hashes shared by at least two rows
     */
    public function resolveFamilies(array $hashes): void
    {
        $dirty = [];
        $this->wireFamilies($hashes, $dirty);
        $this->entityManager->flush();

        $this->materializeAll(array_keys($dirty));
        $this->entityManager->flush();
    }

    /**
     * Elect a canonical per family and point every other member at it.
     *
     * @param string[]         $familyHashes
     * @param array<int, true> $dirty
     */
    private function wireFamilies(array $familyHashes, array &$dirty): void
    {
        if ([] === $familyHashes) {
            return;
        }

        $families = [];
        foreach ($this->eventRepository->findAllByIdentityHashes($familyHashes) as $member) {
            $families[(string) $member->getIdentityHash()][] = $member;
        }

        foreach ($families as $members) {
            $memberIds = array_map(static fn (Event $member): ?int => $member->getId(), $members);
            $canonical = $this->elect($members);
            $dirty[(int) $canonical->getId()] = true;

            foreach ($members as $member) {
                $current = $member->getDuplicateOf();
                $target = $member === $canonical ? null : $canonical;
                if ($current === $target) {
                    continue;
                }

                // Leaving a canonical outside this family: it loses these dates.
                if (null !== $current && !\in_array($current->getId(), $memberIds, true)) {
                    $dirty[(int) $current->getId()] = true;
                }

                $member->setDuplicateOf($target);
            }
        }
    }

    /**
     * The current canonical keeps its role, so public URLs stay put and a choice made
     * by app:events:merge-duplicates is respected; otherwise the oldest row wins.
     *
     * @param non-empty-list<Event> $members
     */
    private function elect(array $members): Event
    {
        $pool = array_values(array_filter($members, static fn (Event $member): bool => null === $member->getDuplicateOf()));
        if ([] === $pool) {
            $pool = $members;
        }

        usort($pool, static fn (Event $a, Event $b): int => ($a->getId() ?? 0) <=> ($b->getId() ?? 0));

        return $pool[0];
    }

    /**
     * @param int[] $canonicalIds
     */
    private function materializeAll(array $canonicalIds): void
    {
        if ([] === $canonicalIds) {
            return;
        }

        $canonicals = [];
        $siblings = [];
        foreach ($this->eventRepository->findFamiliesWithTimesheets($canonicalIds) as $row) {
            $canonical = $row->getDuplicateOf();
            if (null === $canonical) {
                $canonicals[(int) $row->getId()] = $row;

                continue;
            }

            $siblings[(int) $canonical->getId()][] = $row;
        }

        foreach ($canonicals as $id => $canonical) {
            $this->materialize($canonical, $siblings[$id] ?? []);
        }
    }

    /**
     * Rebuild what the canonical inherits from its siblings and realign its range.
     *
     * @param Event[] $siblings the rows pointing at the canonical
     */
    private function materialize(Event $canonical, array $siblings): void
    {
        // Only rows proven to be the same event lend their dates: legacy links stay
        // redirect-only.
        $lenders = array_filter(
            $siblings,
            static fn (Event $sibling): bool => null !== $sibling->getIdentityHash() && $sibling->getIdentityHash() === $canonical->getIdentityHash(),
        );

        // A row that just became a duplicate may still carry dates it inherited as a
        // former canonical: they are its canonical's business now.
        foreach ($siblings as $sibling) {
            foreach ($sibling->getInheritedTimesheets() as $inherited) {
                $sibling->removeTimesheet($inherited);
            }
        }

        $changed = false;

        $own = [];
        foreach ($canonical->getOwnTimesheets() as $timesheet) {
            if (null === $timesheet->getStartAt()) {
                continue;
            }

            $own[$this->key($timesheet->getStartAt(), $timesheet->getEndAt() ?? $timesheet->getStartAt(), $timesheet->getHours())] = true;
        }

        // A canonical without timesheets (imported before that model, or by a parser
        // that only sets a date range) would lose its own date once the range spans
        // the union: materialize it first.
        if ([] === $own && [] !== $lenders && null !== $canonical->getStartDate()) {
            $startAt = $canonical->getStartDate();
            $endAt = $canonical->getEndDate() ?? $startAt;
            $canonical->addTimesheet($this->timesheet($startAt, $endAt, $canonical->getHours(), null));
            $own[$this->key($startAt, $endAt, $canonical->getHours())] = true;
            $changed = true;
        }

        /** @var array<string, array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: string|null, 3: Event}> $desired */
        $desired = [];
        foreach ($lenders as $lender) {
            foreach ($this->tuples($lender) as [$startAt, $endAt, $hours]) {
                $key = $this->key($startAt, $endAt, $hours);
                if (isset($own[$key]) || isset($desired[$key])) {
                    continue;
                }

                $desired[$key] = [$startAt, $endAt, $hours, $lender];
            }
        }

        // Keep the inherited rows still wanted from the same lender, drop the others
        foreach ($canonical->getInheritedTimesheets() as $inherited) {
            $startAt = $inherited->getStartAt();
            $wanted = null === $startAt ? null : ($desired[$this->key($startAt, $inherited->getEndAt() ?? $startAt, $inherited->getHours())] ?? null);
            if (null !== $wanted && $wanted[3]->getId() === $inherited->getSourceEvent()?->getId()) {
                unset($desired[$this->key($wanted[0], $wanted[1], $wanted[2])]);

                continue;
            }

            $canonical->removeTimesheet($inherited);
            $changed = true;
        }

        foreach ($desired as [$startAt, $endAt, $hours, $lender]) {
            $canonical->addTimesheet($this->timesheet($startAt, $endAt, $hours, $lender));
            $changed = true;
        }

        // Sessions are indexed and the search listener only watches the event row: an
        // inherited date coming or going must mark the canonical updated.
        if ($changed) {
            $canonical->setUpdatedAt(new DateTimeImmutable());
        }

        $this->realign($canonical);
    }

    /**
     * A row's own dates as [start, end, hours] tuples, synthesized from its date range
     * when it has no timesheet of its own.
     *
     * @return list<array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: string|null}>
     */
    private function tuples(Event $event): array
    {
        $tuples = [];
        foreach ($event->getOwnTimesheets() as $timesheet) {
            $startAt = $timesheet->getStartAt();
            if (null === $startAt) {
                continue;
            }

            $tuples[] = [$startAt, $timesheet->getEndAt() ?? $startAt, $timesheet->getHours()];
        }

        if ([] === $tuples && null !== $event->getStartDate()) {
            $tuples[] = [$event->getStartDate(), $event->getEndDate() ?? $event->getStartDate(), $event->getHours()];
        }

        return $tuples;
    }

    /**
     * Widen or narrow the canonical's range to what its timesheets, own and
     * inherited, now span. A row without any timesheet keeps its range.
     */
    private function realign(Event $canonical): void
    {
        $start = null;
        $end = null;
        foreach ($canonical->getTimesheets() as $timesheet) {
            $timesheetStart = $timesheet->getStartAt();
            if (null === $timesheetStart) {
                continue;
            }

            $timesheetEnd = $timesheet->getEndAt() ?? $timesheetStart;
            if (null === $start || $timesheetStart < $start) {
                $start = $timesheetStart;
            }

            if (null === $end || $timesheetEnd > $end) {
                $end = $timesheetEnd;
            }
        }

        if (null === $start || null === $end) {
            return;
        }

        $canonical->setStartDate($start);
        $canonical->setEndDate($end);
    }

    private function timesheet(DateTimeImmutable $startAt, DateTimeImmutable $endAt, ?string $hours, ?Event $source): EventTimesheet
    {
        return new EventTimesheet()
            ->setStartAt($startAt)
            ->setEndAt($endAt)
            ->setHours($hours)
            ->setSourceEvent($source);
    }

    /**
     * Timesheets are stored as dates: two sessions on the same day only differ by
     * their hours label, which is therefore part of the key.
     */
    private function key(DateTimeImmutable $startAt, DateTimeImmutable $endAt, ?string $hours): string
    {
        return \sprintf('%s|%s|%s', $startAt->format('Y-m-d'), $endAt->format('Y-m-d'), $hours ?? '');
    }
}
