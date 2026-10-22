<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Outing;

use DateTimeInterface;

/**
 * What changed in an outing since its member last heard of it, among what changes their plans:
 * - a new status that does (OutingChange::fromStatus());
 * - dates that leave out a day they may have chosen: a first day moved later, unless the event had already begun (an
 *   import drops the sessions gone by), or a last day moved earlier. More days, the sessions a family lends its page
 *   included, change nothing;
 * - another start time on the same first day, both known;
 * - another venue, unless it bears the same name in the same city (two records of one venue, merged).
 *
 * An outing already over when its member last heard of it has nothing left to change.
 */
final class OutingChanges
{
    /**
     * @return list<OutingChange> the most serious first
     */
    public static function between(OutingState $known, OutingState $now, DateTimeInterface $today): array
    {
        $day = $today->format('Y-m-d');
        if (null !== $known->end && $known->end < $day) {
            return [];
        }

        $changes = [];
        $status = OutingChange::fromStatus($now->getStatus());
        if (null !== $status && $now->status !== $known->status) {
            $changes[] = $status;
        }

        if (self::datesChanged($known, $now, $day)) {
            $changes[] = OutingChange::Dates;
        }

        if (self::venueChanged($known, $now)) {
            $changes[] = OutingChange::Venue;
        }

        // A new status already says the dates changed: "Reporté", "Nouvelles dates"
        if (\in_array(OutingChange::Postponed, $changes, true) || \in_array(OutingChange::Rescheduled, $changes, true)) {
            $changes = array_values(array_filter($changes, static fn (OutingChange $change): bool => OutingChange::Dates !== $change));
        }

        usort($changes, static fn (OutingChange $a, OutingChange $b): int => array_search($a, OutingChange::cases(), true) <=> array_search($b, OutingChange::cases(), true));

        return $changes;
    }

    private static function datesChanged(OutingState $known, OutingState $now, string $today): bool
    {
        if (null === $known->start || null === $now->start) {
            return false;
        }

        $startedAlready = $known->start < $today;
        if (!$startedAlready && $now->start > $known->start) {
            return true;
        }

        $knownEnd = $known->end ?? $known->start;
        if (($now->end ?? $now->start) < $knownEnd) {
            return true;
        }

        return !$startedAlready
            && $now->start === $known->start
            && null !== $known->time && null !== $now->time
            && $now->time !== $known->time;
    }

    private static function venueChanged(OutingState $known, OutingState $now): bool
    {
        if (null === $known->place || null === $now->place || $known->place === $now->place) {
            return false;
        }

        return self::normalize($known->venue) !== self::normalize($now->venue)
            || self::normalize($known->city) !== self::normalize($now->city);
    }

    private static function normalize(?string $name): string
    {
        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', (string) $name);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', false === $ascii ? '' : $ascii));
    }
}
