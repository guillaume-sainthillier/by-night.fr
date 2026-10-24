<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Sheet;

use App\Enum\TransferFormat;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads a member's spreadsheet: its first sheet, the header on its first row that is not empty, each row numbered as
 * the spreadsheet shows it. Every value comes out as text, the dates as JJ/MM/AAAA and the times as HH:MM whatever
 * Excel stored, so the mapping reads one shape.
 *
 * A CSV may be separated by semicolons (Excel in French), commas or tabs, and encoded in UTF-8 or Windows-1252
 * (Excel's "CSV" on Windows).
 */
final class SheetReader
{
    /** An import's rows at most: a member with more splits their file */
    public const int MAX_ROWS = 1000;

    /**
     * @return array{headers: list<string>, rows: list<SheetRow>}
     *
     * @throws InvalidSheetException
     */
    public function read(string $path, TransferFormat $format): array
    {
        $reader = $this->createReader($path, $format);

        try {
            $reader->open($path);
            $headers = null;
            /** @var array<int, EventColumn> $columns by cell index */
            $columns = [];
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $index => $row) {
                    if (null === $headers) {
                        $labels = array_map(static fn (mixed $value): string => trim(self::toText($value, null)), $row->toArray());
                        // An empty row above the header
                        if ('' === implode('', $labels)) {
                            continue;
                        }

                        $headers = $labels;
                        foreach ($headers as $i => $header) {
                            $column = EventColumn::fromHeader($header);
                            // The first of two columns with the same header wins
                            if (null !== $column && !\in_array($column, $columns, true)) {
                                $columns[$i] = $column;
                            }
                        }

                        continue;
                    }

                    $cells = [];
                    $values = [];
                    foreach ($row->toArray() as $i => $value) {
                        $cells[$i] = self::toText($value, $columns[$i] ?? null);
                        if (isset($columns[$i])) {
                            $values[$columns[$i]->value] = trim($cells[$i]);
                        }
                    }

                    if ('' === implode('', $values)) {
                        continue;
                    }

                    if (self::MAX_ROWS === \count($rows)) {
                        throw new InvalidSheetException(\sprintf('Le fichier dépasse %s lignes : découpez-le en plusieurs imports.', number_format(self::MAX_ROWS, thousands_separator: "\u{a0}")));
                    }

                    $rows[] = new SheetRow((int) $index, array_values($cells), $values);
                }

                // The first sheet only
                break;
            }
        } catch (OpenSpoutException) {
            throw new InvalidSheetException('Le fichier ne peut pas être lu : enregistrez-le en CSV ou en Excel (XLSX) et réessayez.');
        } finally {
            $reader->close();
        }

        if (null === $headers || [] === $columns) {
            throw new InvalidSheetException('La première ligne du fichier doit nommer ses colonnes, comme le modèle à télécharger.');
        }

        foreach (EventColumn::cases() as $column) {
            if ($column->isRequired() && !\in_array($column, $columns, true)) {
                throw new InvalidSheetException(\sprintf('La colonne « %s » manque au fichier.', $column->getLabel()));
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Both readers keep the empty rows, which they would otherwise skip without counting them: a row's number is the
     * one the member sees, an empty CSV line or a blank Excel row (left out of the file) included.
     */
    private function createReader(string $path, TransferFormat $format): ReaderInterface
    {
        return match ($format) {
            TransferFormat::Xlsx => new XlsxReader(new XlsxOptions(SHOULD_PRESERVE_EMPTY_ROWS: true)),
            TransferFormat::Csv => new CsvReader(self::csvOptions($path)),
            TransferFormat::Ics => throw new InvalidArgumentException('A calendar is not imported.'),
        };
    }

    private static function csvOptions(string $path): CsvOptions
    {
        $sample = (string) file_get_contents($path, length: 65536);
        $firstLine = strtok($sample, "\n") ?: '';
        $delimiters = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")];
        arsort($delimiters);

        return new CsvOptions(
            SHOULD_PRESERVE_EMPTY_ROWS: true,
            FIELD_DELIMITER: (string) array_key_first($delimiters),
            ENCODING: mb_check_encoding($sample, 'UTF-8') ? 'UTF-8' : 'Windows-1252',
        );
    }

    /**
     * A cell as text: XLSX gives dates and times as objects (or as a fraction of a day), numbers as numbers.
     */
    private static function toText(mixed $value, ?EventColumn $column): string
    {
        $isTime = EventColumn::StartTime === $column || EventColumn::EndTime === $column;
        $isDate = EventColumn::StartDate === $column || EventColumn::EndDate === $column;

        return match (true) {
            null === $value => '',
            // A date cell left in the "Standard" format holds Excel's serial: days since 30/12/1899
            $isDate && (\is_int($value) || \is_float($value)) && $value >= 1 && $value < 2958466 => new DateTimeImmutable('1899-12-30')->modify(\sprintf('+%d days', (int) $value))->format('d/m/Y'),
            \is_bool($value) => $value ? 'oui' : 'non',
            $value instanceof DateTimeInterface => $value->format($isTime ? 'H:i' : ('00:00' === $value->format('H:i') ? 'd/m/Y' : 'd/m/Y H:i')),
            $value instanceof DateInterval => \sprintf('%02d:%02d', $value->h + 24 * (int) $value->days, $value->i),
            $isTime && \is_float($value) && $value >= 0 && $value < 1 => \sprintf('%02d:%02d', intdiv((int) round($value * 1440), 60), (int) round($value * 1440) % 60),
            \is_float($value) && floor($value) === $value && abs($value) < 1e15 => (string) (int) $value,
            \is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
