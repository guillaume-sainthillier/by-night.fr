<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import\CrossSource;

use App\Entity\Event;
use DateTimeImmutable;

/**
 * Tells whether two events imported from two sources are the same show: the same venue, a day in common, and two
 * titles naming the same show (EventTitleComparator).
 *
 * The venue is the place both imports resolved to: the place comparator already merges the venues of the sources
 * (name and street, in the same town). Two places of the same venue left apart keep their events apart too.
 */
final readonly class CrossSourceMatcher
{
    public function __construct(private EventTitleComparator $titleComparator)
    {
    }

    public function match(Event $left, Event $right): MatchVerdict
    {
        if (!self::isComparable($left) || !self::isComparable($right) || $left->getFromData() === $right->getFromData()) {
            return MatchVerdict::NotComparable;
        }

        $place = $left->getPlace();
        if (null === $place || null === $place->getId() || $place->getId() !== $right->getPlace()?->getId()) {
            return MatchVerdict::OtherVenue;
        }

        if (!self::shareADay($left, $right)) {
            return MatchVerdict::OtherDay;
        }

        return $this->titleComparator->compare((string) $left->getName(), (string) $right->getName(), [
            $place->getName(),
            $left->getPlaceCity() ?? $place->getCity()?->getName(),
        ]);
    }

    /**
     * An event a source still lists: a member's event has no other source, a row its source took back has no show
     * left to match, a draft is not published.
     */
    private static function isComparable(Event $event): bool
    {
        return null !== $event->getFromData()
            && !$event->isRemovedAtSource()
            && !$event->isDraft();
    }

    /**
     * Whether a session of one falls on a day of a session of the other. Their date ranges are not enough: a show
     * that comes back to a venue in spring and in winter spans the months between.
     */
    private static function shareADay(Event $left, Event $right): bool
    {
        foreach (self::ownSessions($left) as $a) {
            $aStart = $a[0]?->format('Y-m-d');
            if (null === $aStart) {
                continue;
            }

            $aEnd = $a[1]?->format('Y-m-d') ?? $aStart;
            foreach (self::ownSessions($right) as $b) {
                $bStart = $b[0]?->format('Y-m-d');
                if (null === $bStart) {
                    continue;
                }

                $bEnd = $b[1]?->format('Y-m-d') ?? $bStart;
                if ($aStart <= $bEnd && $bStart <= $aEnd) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * What its source says of its dates: its own timesheets, or its range without any. Never the dates a canonical
     * inherits from its family: they would keep it matching the very rows it inherited them from.
     *
     * @return list<array{0: DateTimeImmutable|null, 1: DateTimeImmutable|null}> start and end of each session
     */
    private static function ownSessions(Event $event): array
    {
        $sessions = [];
        foreach ($event->getOwnTimesheets() as $timesheet) {
            if (null !== $timesheet->getStartAt()) {
                $sessions[] = [$timesheet->getStartAt(), $timesheet->getEndAt()];
            }
        }

        return [] !== $sessions ? $sessions : [[$event->getStartDate(), $event->getEndDate()]];
    }
}
