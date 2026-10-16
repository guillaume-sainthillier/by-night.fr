<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

/**
 * The file formats of the members' imports and exports. An import reads a spreadsheet (CSV or XLSX); the calendar
 * (ICS) is only exported, it holds too little of an event to import it back.
 */
enum TransferFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Ics = 'ics';

    public static function fromFilename(string $filename): ?self
    {
        return self::tryFrom(strtolower(pathinfo($filename, \PATHINFO_EXTENSION)));
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
            self::Xlsx => 'Excel (XLSX)',
            self::Ics => 'Calendrier (ICS)',
        };
    }

    public function getMimeType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Ics => 'text/calendar',
        };
    }

    public function isImportable(): bool
    {
        return self::Ics !== $this;
    }

    /**
     * @return list<self>
     */
    public static function importable(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $format): bool => $format->isImportable()));
    }
}
