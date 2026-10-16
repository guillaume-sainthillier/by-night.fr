<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Transfer\Sheet;

use App\Enum\TransferFormat;
use App\Transfer\Sheet\SheetWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class SheetWriterTest extends TestCase
{
    public function testAnExcelColumnOffersItsSuggestionsAndTakesAnyOtherValue(): void
    {
        $sheet = $this->sheetXml(['Nom', 'Catégorie', 'Brouillon'], ['category' => ['Concert', 'Théâtre, danse', 'Exposition', 'Concert']]);

        // The suggestions, without the one holding the list's separator nor the repeated one, in "information" style
        self::assertMatchesRegularExpression('#<dataValidation[^>]*type="list"[^>]*errorStyle="information"[^>]*sqref="B2:B1001"[^>]*>\s*<formula1>"Concert,Exposition"</formula1>#', $sheet);
        // "Brouillon" keeps its strict list
        self::assertMatchesRegularExpression('#errorStyle="stop"[^>]*sqref="C2:C1001"#', $sheet);
    }

    public function testASuggestionListStopsWhereExcelWould(): void
    {
        $sheet = $this->sheetXml(['Catégorie'], ['category' => array_map(static fn (int $i): string => str_repeat('x', 20) . $i, range(1, 30))]);

        preg_match('#<formula1>"([^"]*)"</formula1>#', $sheet, $matches);
        self::assertLessThanOrEqual(255, mb_strlen($matches[1]));
        // 9 values of 21 characters and 2 of 22, each with its separator: the 12th would go past 255
        self::assertCount(11, explode(',', $matches[1]));
    }

    /**
     * @param list<string>                $headers
     * @param array<string, list<string>> $suggestions
     */
    private function sheetXml(array $headers, array $suggestions): string
    {
        $path = sys_get_temp_dir() . '/sheet-writer-test-' . bin2hex(random_bytes(4)) . '.xlsx';
        new SheetWriter()->write($path, TransferFormat::Xlsx, $headers, [], $suggestions);

        $zip = new ZipArchive();
        $zip->open($path);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        return $xml;
    }
}
