<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Outing;

use DateTimeImmutable;
use IntlDateFormatter;

/**
 * The sentence of an outing change alert telling each change (OutingChange), in plain text: the HTML part of the
 * e-mail escapes it, the text part prints it as is.
 */
final class OutingChangeWording
{
    public static function sentence(OutingChange $change, OutingState $known, OutingState $now): string
    {
        return match ($change) {
            OutingChange::Cancelled => "L'événement est annulé.",
            OutingChange::Removed => "Son organisateur ne l'annonce plus\u{a0}: renseignez-vous auprès de lui avant de vous déplacer.",
            OutingChange::Postponed => $now->start === $known->start && $now->end === $known->end
                ? "L'événement est reporté, à une date encore inconnue."
                : \sprintf("L'événement est reporté\u{a0}: %s (avant\u{a0}: %s).", self::days($now), self::days($known)),
            OutingChange::Rescheduled => \sprintf("L'événement est reprogrammé\u{a0}: %s (avant\u{a0}: %s).", self::days($now), self::days($known)),
            OutingChange::MovedOnline => "L'événement se tient désormais en ligne.",
            OutingChange::Dates => \sprintf("Nouvelles dates\u{a0}: %s (avant\u{a0}: %s).", self::days($now), self::days($known)),
            OutingChange::Venue => \sprintf("Nouveau lieu\u{a0}: %s (avant\u{a0}: %s).", self::venue($now), self::venue($known)),
        };
    }

    /**
     * "le samedi 24 octobre 2026 à 20h30", "du vendredi 23 octobre au dimanche 25 octobre 2026".
     */
    public static function days(OutingState $state): string
    {
        if (null === $state->start) {
            return 'à préciser';
        }

        if (null === $state->end || $state->end === $state->start) {
            return 'le ' . self::format($state->start, 'EEEE d MMMM y') . (null === $state->time ? '' : ' à ' . str_replace(':', 'h', $state->time));
        }

        return \sprintf('du %s au %s', self::format($state->start, 'EEEE d MMMM'), self::format($state->end, 'EEEE d MMMM y'));
    }

    /**
     * "Le Bikini, Ramonville".
     */
    public static function venue(OutingState $state): string
    {
        $names = array_filter([$state->venue, $state->city], static fn (?string $name): bool => null !== $name && '' !== $name);

        return [] === $names ? 'un lieu encore inconnu' : implode(', ', $names);
    }

    private static function format(string $day, string $pattern): string
    {
        return (string) new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, $pattern)
            ->format(new DateTimeImmutable($day));
    }
}
