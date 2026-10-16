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
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Validation\Rules\ListValidationRule;
use OpenSpout\Writer\XLSX\Validation\ValidationDisplay;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Writes a spreadsheet, streamed row by row: a CSV as Excel in French opens it (semicolons, UTF-8 with its BOM), or
 * an XLSX whose header is bold and whose "Statut" and "Brouillon" columns offer their values in a list.
 * Values are written as text (the dates as JJ/MM/AAAA), as the import reads them back.
 */
final class SheetWriter
{
    /** The rows an XLSX list validation covers below the header */
    private const int VALIDATED_ROWS = SheetReader::MAX_ROWS;

    /**
     * @param list<string>                               $headers
     * @param iterable<list<string|int|float|bool|null>> $rows
     *
     * @return int the rows written, the header left out
     */
    public function write(string $path, TransferFormat $format, array $headers, iterable $rows): int
    {
        $writer = match ($format) {
            TransferFormat::Csv => new CsvWriter(new CsvOptions(FIELD_DELIMITER: ';')),
            TransferFormat::Xlsx => new XlsxWriter(self::xlsxOptions($headers)),
            TransferFormat::Ics => throw new InvalidArgumentException('A calendar is not a spreadsheet.'),
        };

        $writer->openToFile($path);
        $count = 0;

        try {
            $writer->addRow(TransferFormat::Xlsx === $format ? Row::fromValuesWithStyle($headers, new Style()->withFontBold(true)) : Row::fromValues($headers));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
                ++$count;
            }
        } finally {
            $writer->close();
        }

        return $count;
    }

    /**
     * @param list<string> $headers
     */
    private static function xlsxOptions(array $headers): XlsxOptions
    {
        $options = new XlsxOptions();
        $options->setColumnWidth(18, ...range(1, max(1, \count($headers))));

        $lists = [
            EventColumn::Status->value => array_keys(EventStatusLabels::IMPORTABLE),
            EventColumn::Draft->value => ['oui', 'non'],
        ];
        foreach ($headers as $index => $header) {
            $values = $lists[EventColumn::fromHeader($header)?->value ?? ''] ?? null;
            if (null !== $values) {
                $options->addValidation($index, 2, $index, self::VALIDATED_ROWS + 1, new ListValidationRule($values), new ValidationDisplay(errorTitle: $header, error: 'Choisissez une valeur de la liste.'));
            }
        }

        return $options;
    }
}
