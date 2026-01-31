<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

use DateTimeImmutable;

/**
 * @phpstan-type DateRangeType = array{0: DateTimeImmutable, 1: DateTimeImmutable|null}
 * @phpstan-type DateRangePresetType = array<string, DateRangeType>
 * Predefined date range presets for the DateRangeType.
 */
enum DateRangePreset: string
{
    case Anytime = 'anytime';
    case Today = 'today';
    case Tomorrow = 'tomorrow';
    case ThisWeekend = 'this_weekend';
    case ThisWeek = 'this_week';
    case ThisMonth = 'this_month';

    /**
     * Get the human-readable label for this preset.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Anytime => "N'importe quand",
            self::Today => "Aujourd'hui",
            self::Tomorrow => 'Demain',
            self::ThisWeekend => 'Ce week-end',
            self::ThisWeek => 'Cette semaine',
            self::ThisMonth => 'Ce mois',
        };
    }

    /**
     * Get the date range [from, to] for this preset.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable|null}
     */
    public function getDateRange(): array
    {
        return match ($this) {
            self::Anytime => [new DateTimeImmutable('now'), null],
            self::Today => [new DateTimeImmutable('now'), new DateTimeImmutable('now')],
            self::Tomorrow => [new DateTimeImmutable('tomorrow'), new DateTimeImmutable('tomorrow')],
            self::ThisWeekend => [new DateTimeImmutable('friday this week'), new DateTimeImmutable('sunday this week')],
            self::ThisWeek => [new DateTimeImmutable('monday this week'), new DateTimeImmutable('sunday this week')],
            self::ThisMonth => [new DateTimeImmutable('first day of this month'), new DateTimeImmutable('last day of this month')],
        };
    }

    /**
     * Build a ranges array from a list of presets.
     *
     * @param DateRangePreset[] $presets
     *
     * @return array<string, array{0: DateTimeImmutable, 1: DateTimeImmutable|null}>
     */
    public static function buildRanges(array $presets): array
    {
        $ranges = [];
        foreach ($presets as $preset) {
            [$from, $to] = $preset->getDateRange();
            $ranges[$preset->getLabel()] = [$from, $to];
        }

        return $ranges;
    }
}
