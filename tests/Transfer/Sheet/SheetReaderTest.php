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
use App\Transfer\Sheet\EventColumn;
use App\Transfer\Sheet\EventSheetTemplate;
use App\Transfer\Sheet\InvalidSheetException;
use App\Transfer\Sheet\SheetReader;
use App\Transfer\Sheet\SheetRow;
use App\Transfer\Sheet\SheetWriter;
use DateTimeImmutable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SheetReaderTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), array_filter($this->files, is_file(...)));
    }

    /**
     * @return iterable<string, array{TransferFormat}>
     */
    public static function provideFormats(): iterable
    {
        yield 'csv' => [TransferFormat::Csv];
        yield 'xlsx' => [TransferFormat::Xlsx];
    }

    #[DataProvider('provideFormats')]
    public function testTheTemplateReadsBackAsItsExamples(TransferFormat $format): void
    {
        $path = $this->file($format);
        new EventSheetTemplate(new SheetWriter())->write($path, $format);

        $sheet = new SheetReader()->read($path, $format);

        self::assertSame(array_map(static fn (EventColumn $column): string => $column->getLabel(), EventColumn::cases()), $sheet['headers']);
        self::assertCount(3, $sheet['rows']);
        [$first, $second] = $sheet['rows'];
        self::assertSame(2, $first->number);
        self::assertSame('CONCERT-JAZZ', $first->get(EventColumn::Reference));
        self::assertSame('Soirée jazz au bord de l’eau', $first->get(EventColumn::Name));
        self::assertSame('20:30', $first->get(EventColumn::StartTime));
        self::assertSame('CONCERT-JAZZ', $second->get(EventColumn::Reference));
        self::assertSame('', $second->get(EventColumn::Name));
        self::assertCount(\count(EventColumn::cases()), $first->cells);
    }

    public function testHeadersAreFoundWhateverTheirCaseAccentsAndOrder(): void
    {
        $sheet = $this->readCsv("date de debut,NOM,Colonne à moi\n12/03/2027,Concert,x\n");

        self::assertSame(['start_date' => '12/03/2027', 'name' => 'Concert'], $sheet['rows'][0]->values);
        self::assertSame(['12/03/2027', 'Concert', 'x'], $sheet['rows'][0]->cells);
    }

    public function testACsvFromExcelOnWindowsIsReadAsWindows1252WithSemicolons(): void
    {
        $sheet = $this->readCsv((string) mb_convert_encoding("Nom;Date de début;Lieu\nThéâtre;12/03/2027;Salle Pleyel\n", 'Windows-1252', 'UTF-8'));

        self::assertSame('Théâtre', $sheet['rows'][0]->get(EventColumn::Name));
        self::assertSame('Salle Pleyel', $sheet['rows'][0]->get(EventColumn::Place));
    }

    public function testEmptyRowsAreSkippedAndRowsKeepTheirNumber(): void
    {
        $sheet = $this->readCsv("Nom;Date de début\n;\nConcert;12/03/2027\n");

        self::assertCount(1, $sheet['rows']);
        self::assertSame(3, $sheet['rows'][0]->number);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideLineEndings(): iterable
    {
        yield 'unix' => ["\n"];
        yield 'windows' => ["\r\n"];
    }

    #[DataProvider('provideLineEndings')]
    public function testEmptyCsvLinesCountInTheRowNumbers(string $eol): void
    {
        $sheet = $this->readCsv(implode($eol, ['', 'Nom;Date de début', '', 'Concert;12/03/2027', '', '', 'Théâtre;13/03/2027', '']));

        self::assertSame(['Nom', 'Date de début'], $sheet['headers']);
        self::assertSame([4, 7], array_map(static fn (SheetRow $row): int => $row->number, $sheet['rows']));
    }

    public function testBlankExcelRowsCountInTheRowNumbers(): void
    {
        $path = $this->file(TransferFormat::Xlsx);
        $writer = new XlsxWriter();
        $writer->openToFile($path);
        // The writer leaves the empty rows out of the file, as Excel does
        foreach ([[], ['Nom', 'Date de début'], [], ['Concert', '12/03/2027'], [], [], ['Théâtre', '13/03/2027']] as $values) {
            $writer->addRow(Row::fromValues($values));
        }
        $writer->close();

        $sheet = new SheetReader()->read($path, TransferFormat::Xlsx);

        self::assertSame(['Nom', 'Date de début'], $sheet['headers']);
        self::assertSame([4, 7], array_map(static fn (SheetRow $row): int => $row->number, $sheet['rows']));
    }

    public function testExcelDatesAndTimesAreReadAsText(): void
    {
        $path = $this->file(TransferFormat::Xlsx);
        $writer = new XlsxWriter();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Nom', 'Date de début', 'Heure de début', 'Code postal']));
        $writer->addRow(Row::fromValues(['Concert', new DateTimeImmutable('2027-03-12'), 0.875, 31000]));
        $writer->close();

        $row = new SheetReader()->read($path, TransferFormat::Xlsx)['rows'][0];

        self::assertSame('12/03/2027', $row->get(EventColumn::StartDate));
        self::assertSame('21:00', $row->get(EventColumn::StartTime));
        self::assertSame('31000', $row->get(EventColumn::PostalCode));
    }

    public function testARequiredColumnMustBeThere(): void
    {
        $this->expectException(InvalidSheetException::class);
        $this->expectExceptionMessage('La colonne « Date de début » manque au fichier.');

        $this->readCsv("Nom;Lieu\nConcert;Salle\n");
    }

    public function testAFileWithoutKnownHeadersIsTurnedDown(): void
    {
        $this->expectException(InvalidSheetException::class);
        $this->expectExceptionMessage('La première ligne du fichier doit nommer ses colonnes');

        $this->readCsv("Concert;12/03/2027\n");
    }

    public function testTooManyRowsAreTurnedDown(): void
    {
        $this->expectException(InvalidSheetException::class);
        $this->expectExceptionMessage("Le fichier dépasse 1\u{a0}000 lignes");

        $this->readCsv("Nom;Date de début\n" . str_repeat("Concert;12/03/2027\n", SheetReader::MAX_ROWS + 1));
    }

    public function testAFileThatIsNoSpreadsheetIsTurnedDown(): void
    {
        $path = $this->file(TransferFormat::Xlsx);
        file_put_contents($path, 'not a zip');

        $this->expectException(InvalidSheetException::class);
        $this->expectExceptionMessage('Le fichier ne peut pas être lu');

        new SheetReader()->read($path, TransferFormat::Xlsx);
    }

    /**
     * @return array{headers: list<string>, rows: list<SheetRow>}
     */
    private function readCsv(string $content): array
    {
        $path = $this->file(TransferFormat::Csv);
        file_put_contents($path, $content);

        return new SheetReader()->read($path, TransferFormat::Csv);
    }

    private function file(TransferFormat $format): string
    {
        return $this->files[] = sys_get_temp_dir() . '/sheet-reader-test-' . bin2hex(random_bytes(6)) . '.' . $format->value;
    }
}
