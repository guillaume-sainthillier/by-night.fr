<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use DateTime;

/**
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
     * @return array{0: string, 1: string|null}
     */
    public function getDateRange(): array
    {
        return match ($this) {
            self::Anytime => [(new DateTime('now'))->format('Y-m-d'), null],
            self::Today => [(new DateTime('now'))->format('Y-m-d'), (new DateTime('now'))->format('Y-m-d')],
            self::Tomorrow => [(new DateTime('tomorrow'))->format('Y-m-d'), (new DateTime('tomorrow'))->format('Y-m-d')],
            self::ThisWeekend => [(new DateTime('friday this week'))->format('Y-m-d'), (new DateTime('sunday this week'))->format('Y-m-d')],
            self::ThisWeek => [(new DateTime('monday this week'))->format('Y-m-d'), (new DateTime('sunday this week'))->format('Y-m-d')],
            self::ThisMonth => [(new DateTime('first day of this month'))->format('Y-m-d'), (new DateTime('last day of this month'))->format('Y-m-d')],
        };
    }

    /**
     * Build a ranges array from a list of presets.
     *
     * @param DateRangePreset[] $presets
     *
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function buildRanges(array $presets): array
    {
        $ranges = [];
        foreach ($presets as $preset) {
            $ranges[$preset->getLabel()] = $preset->getDateRange();
        }

        return $ranges;
    }

    /**
     * Get all presets as a ranges array.
     *
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function all(): array
    {
        return self::buildRanges(self::cases());
    }
}
