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
use App\Enum\DuplicateReason;
use App\Enum\EventStatus;
use App\Parser\AffiliateParsers;
use App\Repository\CrossSourceLinkRepository;
use App\Repository\EventRepository;
use App\Utils\ObjectKey;
use App\Utils\StartingPrice;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Groups the rows that describe the same event under distinct external ids into a
 * family, and materializes the family on one of them.
 *
 * Some sources publish one record per session of an event: OpenAgenda organizers
 * duplicate an event for each date instead of adding a timing to it. And several
 * sources sell or list the same show: Fnac, CDiscount and SeeTickets each import a
 * concert. Every record keeps its own row, external id, exploration and timesheets,
 * so the import keeps tracking each source record on its own. Two rows belong to the
 * same family when something proves they are the same event: a shared identity hash
 * (EventContentHasher::identity(), one source), or a cross-source link
 * (CrossSourceLink, written by CrossSourceLinker); and so do the rows joined to them.
 *  - one member is the canonical event, the others point at it through duplicateOf
 *    (301 redirect, excluded from the search index and the listings), with the
 *    reason of the link;
 *  - the canonical carries the union of the family's dates: its own timesheets plus
 *    an inherited copy of each sibling's own timesheets, tagged with the sibling it
 *    comes from, so the copy is rebuilt on every change and goes away with the
 *    sibling (ON DELETE CASCADE);
 *  - its start and end dates span that union, since the listings filter and sort
 *    on them;
 *  - its starting price is the lowest the family sells the show for, and its page
 *    shows the largest picture of the family when much larger than its own.
 *
 * Links made by hand, or before the identity hash, are left as they are: a row linked
 * without a reason and without a hash is neither moved nor asked to lend its dates.
 */
final readonly class EventFamilyResolver
{
    /** How much larger a member's picture must be for the canonical to show it instead of its own */
    private const float BORROWED_PICTURE_RATIO = 1.5;

    public function __construct(
        private EventRepository $eventRepository,
        private CrossSourceLinkRepository $crossSourceLinkRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Resolve the families of the given events, just imported or linked: the ones they
     * now belong to and the ones they may have left. Runs in the import transaction,
     * after the batch has been merged and the EntityManager cleared, or in the one of
     * CrossSourceLinker, its events still managed: it reads them through the identity map.
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
        $families = $this->gather($candidates);

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

            // Nothing proves any longer the row describes the event of its canonical: set
            // it free. If it has a family of its own, wireFamilies() re-links it below.
            if (!$families->together($candidate, $canonical) && self::isResolverLink($candidate, $canonical)) {
                $candidate->setDuplicateOf(null);
                $dirty[(int) $candidate->getId()] = true;
            }
        }

        $wired = $this->wireFamilies($families, $dirty);
        $this->entityManager->flush();

        // A canonical some member left may not belong to any family gathered: its own still lends it what it shows
        $strays = array_values(array_filter(array_keys($dirty), static fn (int $id): bool => !$families->has($id)));
        if ([] !== $strays) {
            $this->gather($this->eventRepository->findBy(['id' => $strays]), $families);
        }

        $this->materializeAll(array_keys($dirty), $families);
        $this->entityManager->flush();

        if ($wired > 0 || [] !== $dirty) {
            $this->logger->info('Resolved {families} event families, rebuilt {canonicals} canonical(s)', [
                'families' => $wired,
                'canonicals' => \count($dirty),
            ]);
        }
    }

    /**
     * The candidates and every row proven to be the same event as one of them, step by
     * step: the rows sharing an identity hash, the rows linked across sources, then the
     * rows sharing their hash or linked to them, until no row is left to add.
     *
     * @param Event[]       $candidates
     * @param EventFamilies $families   the families gathered so far, to add to
     */
    private function gather(array $candidates, EventFamilies $families = new EventFamilies()): EventFamilies
    {
        $added = array_values(array_filter($candidates, $families->add(...)));

        /** @var array<string, int> $firstByHash the first row met of each identity hash */
        $firstByHash = [];
        /** @var array<string, true> $loadedHashes the hashes whose rows are all loaded */
        $loadedHashes = [];

        while ([] !== $added) {
            $hashes = [];
            foreach ($added as $event) {
                $hash = $event->getIdentityHash();
                if (null !== $hash && !isset($loadedHashes[$hash])) {
                    $hashes[$hash] = $loadedHashes[$hash] = true;
                }
            }

            $next = [];
            foreach ($this->eventRepository->findAllByIdentityHashes($this->eventRepository->findSharedIdentityHashes(array_keys($hashes))) as $member) {
                if ($families->add($member)) {
                    $next[] = $member;
                }
            }

            $links = array_filter(
                $this->crossSourceLinkRepository->findTouching(array_map(static fn (Event $event): int => (int) $event->getId(), $added)),
                static fn (array $link): bool => !$link['keptApart'],
            );
            $missing = [];
            foreach ($links as $link) {
                foreach ([$link['eventId'], $link['linkedEventId']] as $id) {
                    if (!$families->has($id)) {
                        $missing[$id] = $id;
                    }
                }
            }

            if ([] !== $missing) {
                foreach ($this->eventRepository->findBy(['id' => array_values($missing)]) as $linked) {
                    if ($families->add($linked)) {
                        $next[] = $linked;
                    }
                }
            }

            // Every row of a shared hash is loaded with the first one met: join them on it
            foreach ([...$added, ...$next] as $event) {
                $hash = $event->getIdentityHash();
                if (null === $hash) {
                    continue;
                }

                $firstByHash[$hash] ??= (int) $event->getId();
                $families->join($firstByHash[$hash], (int) $event->getId());
            }

            foreach ($links as $link) {
                $families->join($link['eventId'], $link['linkedEventId']);
            }

            $added = $next;
        }

        return $families;
    }

    /**
     * Whether the link is one the resolver made or would make, which it takes back when
     * nothing proves it any longer. A link made by hand, or made before the identity hash,
     * between rows the resolver cannot compare, is left as it is.
     */
    private static function isResolverLink(Event $duplicate, Event $canonical): bool
    {
        return null !== $duplicate->getDuplicateReason()
            || (null !== $duplicate->getIdentityHash() && null !== $canonical->getIdentityHash());
    }

    /**
     * Elect a canonical per family and point every other member at it, with the rows
     * outside the family that pointed at a member (links made before the identity hash):
     * a redirect never leads to another one.
     *
     * @param array<int, true> $dirty
     *
     * @return int the number of families wired
     */
    private function wireFamilies(EventFamilies $families, array &$dirty): int
    {
        /** @var array<int, Event> $canonicalOf the canonical of each member now a duplicate */
        $canonicalOf = [];

        $wired = 0;
        foreach ($families->families() as $members) {
            ++$wired;
            $memberIds = array_map(static fn (Event $member): ?int => $member->getId(), $members);
            $canonical = $this->elect($members);
            $dirty[(int) $canonical->getId()] = true;

            foreach ($members as $member) {
                $current = $member->getDuplicateOf();
                $target = $member === $canonical ? null : $canonical;
                if (null !== $target) {
                    $canonicalOf[(int) $member->getId()] = $target;
                }

                $reason = null === $target ? null : self::reasonOf($member, $canonical);
                if ($current === $target && $member->getDuplicateReason() === $reason) {
                    continue;
                }

                // Leaving a canonical outside this family: it loses these dates.
                if (null !== $current && !\in_array($current->getId(), $memberIds, true)) {
                    $dirty[(int) $current->getId()] = true;
                }

                $member->setDuplicateOf($target, $reason);
            }
        }

        // Read before the flush: a member that pointed at another one is rewired above already.
        // A row followed keeps its reason: a link without one stays one made by hand
        if ([] !== $canonicalOf) {
            foreach ($this->eventRepository->findBy(['duplicateOf' => array_keys($canonicalOf)]) as $row) {
                $canonical = $canonicalOf[(int) $row->getDuplicateOf()?->getId()] ?? null;
                if (null === $canonical) {
                    continue;
                }

                $row->setDuplicateOf($canonical, $row->getDuplicateReason());
                $dirty[(int) $canonical->getId()] = true;
            }
        }

        return $wired;
    }

    private static function reasonOf(Event $member, Event $canonical): DuplicateReason
    {
        return null !== $member->getIdentityHash() && $member->getIdentityHash() === $canonical->getIdentityHash()
            ? DuplicateReason::SameIdentity
            : DuplicateReason::SameShow;
    }

    /**
     * The current canonical keeps its role, so public URLs stay put and a link made
     * before the identity hash is respected: a canonical of the family already followed
     * by some of its members first, then any row still a page of its own. Between
     * several (the pages of two sources, gathered for the first time), the source whose
     * page shows the event best wins, then the oldest row. A row its source no longer
     * lists only keeps the role when the whole family is gone: its page would hide the
     * dates the others still have.
     *
     * @param non-empty-list<Event> $members
     */
    private function elect(array $members): Event
    {
        $live = array_values(array_filter($members, static fn (Event $member): bool => !$member->isRemovedAtSource()));
        if ([] === $live) {
            $live = $members;
        }

        $pool = array_values(array_filter($live, static fn (Event $member): bool => null === $member->getDuplicateOf()));
        if ([] === $pool) {
            $pool = $live;
        }

        $followed = [];
        foreach ($members as $member) {
            $canonical = $member->getDuplicateOf();
            if (null !== $canonical) {
                $followed[(int) $canonical->getId()] = true;
            }
        }

        $rank = static fn (Event $event): array => [isset($followed[(int) $event->getId()]) ? 0 : 1, AffiliateParsers::pageRank($event->getFromData()), $event->getId() ?? 0];
        usort($pool, static fn (Event $a, Event $b): int => $rank($a) <=> $rank($b));

        return $pool[0];
    }

    /**
     * @param int[] $canonicalIds
     */
    private function materializeAll(array $canonicalIds, EventFamilies $families): void
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
            $this->materialize($canonical, $siblings[$id] ?? [], $families);
        }
    }

    /**
     * Rebuild what the canonical inherits from its siblings and realign its range.
     *
     * @param Event[] $siblings the rows pointing at the canonical
     */
    private function materialize(Event $canonical, array $siblings, EventFamilies $families): void
    {
        // Only rows proven to be the same event lend their dates: legacy links stay
        // redirect-only. A row its source no longer lists has no date left to lend.
        $lenders = array_values(array_filter(
            $siblings,
            static fn (Event $sibling): bool => $families->together($sibling, $canonical) && !$sibling->isRemovedAtSource(),
        ));

        // A row that just became a duplicate may still carry what it took from its family
        // as a former canonical (dates, a picture, a price): they are its canonical's
        // business now.
        foreach ($siblings as $sibling) {
            foreach ($sibling->getInheritedTimesheets() as $inherited) {
                $sibling->removeTimesheet($inherited);
            }

            $sibling->setPictureFrom(null);
            $sibling->setStartingPrice(StartingPrice::fromPrices($sibling->getPrices()));
        }

        $changed = false;

        $own = [];
        foreach ($canonical->getOwnTimesheets() as $timesheet) {
            if (null === $timesheet->getStartAt()) {
                continue;
            }

            $own[self::key($timesheet)] = true;
        }

        // A canonical without timesheets (imported before that model, or by a parser
        // that only sets a date range) would lose its own date once the range spans
        // the union: materialize it first.
        if ([] === $own && [] !== $lenders && null !== $canonical->getStartDate()) {
            [$session] = $this->sessions($canonical);
            $materialized = $this->timesheet($session, null);
            $canonical->addTimesheet($materialized);
            $own[self::key($materialized)] = true;
            $changed = true;
        }

        // A record of the same event lends each session it lists. Another source describes
        // the same performances in its own words ("À 20h", no time at all): it only lends a
        // day the page does not show yet, or the same day at another time.
        $sameIdentity = static fn (Event $lender): bool => null !== $lender->getIdentityHash() && $lender->getIdentityHash() === $canonical->getIdentityHash();
        usort($lenders, static fn (Event $a, Event $b): int => [$sameIdentity($a) ? 0 : 1, $a->getId()] <=> [$sameIdentity($b) ? 0 : 1, $b->getId()]);

        /** @var array<string, list<string|null>> $shownDays the start times shown each day */
        $shownDays = [];
        foreach ($canonical->getOwnTimesheets() as $timesheet) {
            if (null !== $timesheet->getStartAt()) {
                $shownDays[self::day($timesheet)][] = $timesheet->getStartTime()?->format('H:i');
            }
        }

        /** @var array<string, array{0: EventTimesheet, 1: Event}> $desired */
        $desired = [];
        foreach ($lenders as $lender) {
            foreach ($this->sessions($lender) as $session) {
                $key = self::key($session);
                if (isset($own[$key]) || isset($desired[$key])) {
                    continue;
                }

                $day = self::day($session);
                $time = $session->getStartTime()?->format('H:i');
                if (!$sameIdentity($lender) && isset($shownDays[$day])
                    && (null === $time || \in_array(null, $shownDays[$day], true) || \in_array($time, $shownDays[$day], true))) {
                    continue;
                }

                $desired[$key] = [$session, $lender];
                $shownDays[$day][] = $time;
            }
        }

        // Keep the inherited rows still wanted from the same lender, drop the others
        foreach ($canonical->getInheritedTimesheets() as $inherited) {
            $key = self::key($inherited);
            if (isset($desired[$key]) && $desired[$key][1]->getId() === $inherited->getSourceEvent()?->getId()) {
                unset($desired[$key]);

                continue;
            }

            $canonical->removeTimesheet($inherited);
            $changed = true;
        }

        foreach ($desired as [$session, $lender]) {
            $canonical->addTimesheet($this->timesheet($session, $lender));
            $changed = true;
        }

        // Sessions are indexed and the search listener only watches the event row: an
        // inherited date coming or going must mark the canonical updated.
        if ($changed) {
            $canonical->setUpdatedAt(new DateTimeImmutable());
        }

        $this->realign($canonical);

        $pictureFrom = self::pictureFrom($canonical, $lenders);
        if ($pictureFrom !== $canonical->getPictureFrom()) {
            $canonical->setPictureFrom($pictureFrom);
        }

        $startingPrice = self::startingPrice($canonical, $lenders);
        if ($startingPrice !== $canonical->getStartingPrice()) {
            $canonical->setStartingPrice($startingPrice);
        }
    }

    /**
     * The member whose picture the canonical's page shows: the largest of the family, when
     * it is at least BORROWED_PICTURE_RATIO times the size of the canonical's own (a
     * CDiscount poster in 2048px beside Fnac's 222px), or any when the canonical has none.
     * A picture uploaded by a member is never replaced, and a picture taken down on request
     * is neither replaced nor lent: the request named the show, not one of its sources.
     *
     * @param list<Event> $lenders
     */
    private static function pictureFrom(Event $canonical, array $lenders): ?Event
    {
        if (null !== $canonical->getImageRemovedAt() || '' !== (string) $canonical->getImage()->getName()) {
            return null;
        }

        $best = null;
        $bestArea = self::pictureArea($canonical) * self::BORROWED_PICTURE_RATIO;
        foreach ($lenders as $lender) {
            if (null !== $lender->getImageRemovedAt()) {
                continue;
            }

            $area = self::pictureArea($lender);
            if ($area > $bestArea) {
                $best = $lender;
                $bestArea = $area;
            }
        }

        return $best;
    }

    /**
     * The pixels of the picture downloaded from its source, 0 without one.
     */
    private static function pictureArea(Event $event): float
    {
        $picture = $event->getImageSystem();
        $dimensions = $picture->getDimensions();
        if ('' === (string) $picture->getName() || null === $dimensions) {
            return 0.0;
        }

        return (float) $dimensions[0] * (float) $dimensions[1];
    }

    /**
     * The lowest price to get in that the agenda filters on, among the members still on
     * sale, the canonical included; when none is, among those not cancelled, so that a
     * show sold out everywhere keeps its price. A show some source sells a ticket for is no
     * free show, whatever another one writes ("Gratuit" beside a 39 € ticket): free only
     * when every price the family gives says so.
     *
     * @param list<Event> $lenders
     */
    private static function startingPrice(Event $canonical, array $lenders): ?float
    {
        $rows = [$canonical, ...$lenders];
        $onSale = array_filter($rows, static fn (Event $row): bool => !\in_array($row->getStatus(), [EventStatus::SoldOut, EventStatus::Cancelled], true));

        return self::lowestPrice($onSale)
            ?? self::lowestPrice(array_filter($rows, static fn (Event $row): bool => $row === $canonical || EventStatus::Cancelled !== $row->getStatus()));
    }

    /**
     * @param Event[] $rows
     */
    private static function lowestPrice(array $rows): ?float
    {
        $paying = [];
        $free = false;
        foreach ($rows as $row) {
            $price = StartingPrice::fromPrices($row->getPrices());
            if (null === $price) {
                continue;
            }

            if ($price > 0) {
                $paying[] = $price;
            } else {
                $free = true;
            }
        }

        if ([] !== $paying) {
            return min($paying);
        }

        return $free ? 0.0 : null;
    }

    /**
     * A row's own sessions, synthesized from its date range when it has no timesheet of
     * its own (see Event::getSessions(), which also counts the inherited ones).
     *
     * @return list<EventTimesheet>
     */
    private function sessions(Event $event): array
    {
        $sessions = array_values(array_filter(
            $event->getOwnTimesheets()->toArray(),
            static fn (EventTimesheet $timesheet): bool => null !== $timesheet->getStartAt(),
        ));

        if ([] === $sessions && null !== $event->getStartDate()) {
            $sessions[] = new EventTimesheet()
                ->setStartAt($event->getStartDate())
                ->setEndAt($event->getEndDate() ?? $event->getStartDate())
                ->setStartTime($event->getStartTime())
                ->setEndTime($event->getEndTime())
                ->setHours($event->getHours());
        }

        return $sessions;
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

    /**
     * A copy of a session for the canonical: lent by $source, or its own when null.
     */
    private function timesheet(EventTimesheet $session, ?Event $source): EventTimesheet
    {
        $startAt = $session->getStartAt();

        return new EventTimesheet()
            ->setStartAt($startAt)
            ->setEndAt($session->getEndAt() ?? $startAt)
            ->setStartTime($session->getStartTime())
            ->setEndTime($session->getEndTime())
            ->setHours($session->getHours())
            ->setSourceEvent($source);
    }

    /**
     * The days a session spans, whatever its times.
     */
    private static function day(EventTimesheet $timesheet): string
    {
        return $timesheet->getStartAt()?->format('Y-m-d') . '/' . ($timesheet->getEndAt() ?? $timesheet->getStartAt())?->format('Y-m-d');
    }

    private static function key(EventTimesheet $timesheet): string
    {
        return ObjectKey::timesheet($timesheet->getStartAt(), $timesheet->getEndAt(), $timesheet->getStartTime(), $timesheet->getEndTime(), $timesheet->getHours());
    }
}
