<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use DateTimeImmutable;

/**
 * Reads the hours label of a session back into its times, when it says nothing else: what the sources wrote from the
 * times they give ("À 20h30", "De 10h00 à 18h00", "À 20:30") and what the organizers type ("20h", "21h-05h",
 * "de 21h à minuit."). Anything more ("À partir de 19h", "Jeudi à 20h", "À 20h, de 21h à minuit") is not a slot and
 * stays a label.
 */
final class HoursLabel
{
    private const string TIME = '(?:(\d{1,2})\s*[h:]\s*(\d{2})?|minuit|midi)';

    /**
     * The start and the end of the slot, the end null for a mere time; null when the label is not a plain slot.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable|null}|null
     */
    public static function parse(?string $label): ?array
    {
        $label = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $label ?? ''), " .\u{a0}"));
        if ('' === $label) {
            return null;
        }

        if (1 === preg_match('/^(?:(?:à|a) )?(' . self::TIME . ')$/u', $label, $matches)) {
            $start = self::time($matches[1]);

            return null === $start ? null : [$start, null];
        }

        if (1 === preg_match('/^(?:de )?(' . self::TIME . ') ?(?:à|a|-|–|\/) ?(' . self::TIME . ')$/u', $label, $matches)) {
            $start = self::time($matches[1]);
            $end = self::time($matches[4]);

            return null === $start || null === $end ? null : [$start, $end];
        }

        return null;
    }

    /**
     * "20h", "20h30", "20:30", "9 h", "minuit", "midi"; null for a time that is none ("25h", "20h75").
     */
    private static function time(string $time): ?DateTimeImmutable
    {
        [$hour, $minute] = match ($time) {
            'minuit' => [0, 0],
            'midi' => [12, 0],
            default => preg_match('/^(\d{1,2})\s*[h:]\s*(\d{2})?$/', $time, $matches) ? [(int) $matches[1], (int) ($matches[2] ?? 0)] : [99, 0],
        };

        if ($hour > 24 || $minute > 59 || (24 === $hour && 0 !== $minute)) {
            return null;
        }

        // As the other times of the import: the date is not read
        return DateTimeImmutable::createFromFormat('!H:i', \sprintf('%02d:%02d', $hour % 24, $minute)) ?: null;
    }
}
