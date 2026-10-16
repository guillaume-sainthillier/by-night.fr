<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Transfer\Import;

use App\Enum\EventImportStatus;
use App\Factory\EventFactory;
use App\Tests\AppKernelTestCase;
use App\Tests\Transfer\TransferTestTrait;
use DateTimeImmutable;

final class EventImportCheckerTest extends AppKernelTestCase
{
    use TransferTestTrait;
    private const string HEADER = "Référence externe;ID;Nom;Description;Date de début;Heure de début;Lieu;Code postal;Ville\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTransfers();
    }

    public function testValidEventsAreKeptAndTheOthersReportedWithoutSavingAnything(): void
    {
        $date = new DateTimeImmutable('+1 month');
        $import = $this->check(self::HEADER
            . "JAZZ;;Soirée jazz;Deux soirs de jazz en plein air.;{$date->format('d/m/Y')};20:30;La Guinguette;31000;Toulouse\n"
            . "JAZZ;;;;{$date->modify('+1 day')->format('d/m/Y')};20:30;;;\n"
            . "EXPO;;Exposition;Court;{$date->format('d/m/Y')};;Galerie;31000;Toulouse\n"
            . ";;Sans référence;Une description assez longue.;{$date->format('d/m/Y')};;Salle;31000;Toulouse\n"
            . "THEATRE;;Théâtre;Une pièce en trois actes.;32/13/2027;;Salle;31000;Toulouse\n");

        self::assertSame(EventImportStatus::Checked, $import->getStatus());
        self::assertSame([5, 4, 4, 1, 3], [$import->getRowCount(), $import->getEventCount(), $import->getProcessedCount(), $import->getValidCount(), $import->getInvalidCount()]);
        self::assertSame(0, EventFactory::count(['user' => $this->user]));

        $errors = array_column($import->getErrors(), 'message', 'row');
        self::assertSame([4, 5, 6], array_keys($errors));
        self::assertStringContainsString('« 32/13/2027 » n’est pas une date', $errors[6]);
        self::assertStringContainsString('Donnez une référence', $errors[5]);

        $kept = $this->readStored((string) $import->getSuccessFilePath());
        self::assertSame(['JAZZ', 'JAZZ'], array_map(static fn ($row): string => $row->cells[0], $kept['rows']));

        $turnedDown = $this->readStored((string) $import->getErrorFilePath());
        self::assertSame('Erreurs', $turnedDown['headers'][9]);
        self::assertSame(['EXPO', '', 'THEATRE'], array_map(static fn ($row): string => $row->cells[0], $turnedDown['rows']));
        self::assertStringContainsString('Date de début : « 32/13/2027 »', $turnedDown['rows'][2]->cells[9]);
    }

    public function testRowsOfOneEventMustNotContradictEachOther(): void
    {
        $date = (new DateTimeImmutable('+1 month'))->format('d/m/Y');
        $import = $this->check(self::HEADER
            . "JAZZ;;Soirée jazz;Deux soirs de jazz en plein air.;{$date};20:30;La Guinguette;31000;Toulouse\n"
            . "JAZZ;;Autre nom;;{$date};21:00;;;\n");

        self::assertSame(1, $import->getInvalidCount());
        self::assertSame(3, $import->getErrors()[0]['row']);
        self::assertSame('Nom', $import->getErrors()[0]['column']);
        self::assertStringContainsString('contredit la ligne 2', $import->getErrors()[0]['message']);
    }

    public function testAnotherMembersEventCannotBeUpdated(): void
    {
        $someoneElses = EventFactory::createOne();
        $date = (new DateTimeImmutable('+1 month'))->format('d/m/Y');
        $import = $this->check(self::HEADER . ";{$someoneElses->getId()};Soirée;Une description assez longue.;{$date};;Salle;31000;Toulouse\n");

        self::assertSame(1, $import->getInvalidCount());
        self::assertSame('ID', $import->getErrors()[0]['column']);
    }

    public function testAnEventLookingLikeOneOnlineIsKeptWithAWarning(): void
    {
        $date = new DateTimeImmutable('+1 month');
        $this->lookalikes = [EventFactory::createOne(['name' => 'Soirée jazz', 'startDate' => $date, 'endDate' => $date, 'latitude' => 43.6, 'longitude' => 1.44])];

        $import = $this->check(self::HEADER . "JAZZ;;Soirée jazz;Deux soirs de jazz en plein air.;{$date->format('d/m/Y')};20:30;La Guinguette;31000;Toulouse\n");

        self::assertSame([1, 0], [$import->getValidCount(), $import->getInvalidCount()]);
        self::assertSame(1, $import->getWarningCount());
        self::assertStringStartsWith('https://by-night.test/', $import->getWarnings()[0]['url'] ?? '');
    }

    public function testSearchBeingDownCostsTheHintsNotTheCheck(): void
    {
        $this->searchDownCalls = 0;
        $date = (new DateTimeImmutable('+1 month'))->format('d/m/Y');

        $import = $this->check(self::HEADER
            . "JAZZ;;Soirée jazz;Deux soirs de jazz en plein air.;{$date};20:30;La Guinguette;31000;Toulouse\n"
            . "BLUES;;Soirée blues;Une soirée de blues en plein air.;{$date};20:30;La Guinguette;31000;Toulouse\n");

        self::assertSame([2, 0], [$import->getValidCount(), $import->getWarningCount()]);
        self::assertSame(1, $this->searchDownCalls, 'Asked once: its client retries for a minute each time');
    }

    public function testAFileWithoutARequiredColumnFailsTheCheck(): void
    {
        $import = $this->check("Nom;Lieu\nConcert;Salle\n");

        self::assertSame(EventImportStatus::CheckFailed, $import->getStatus());
        self::assertSame('La colonne « Date de début » manque au fichier.', $import->getFailureReason());
    }

    public function testACancelledImportIsNotChecked(): void
    {
        $import = $this->check(self::HEADER, EventImportStatus::Cancelled);

        self::assertSame(EventImportStatus::Cancelled, $import->getStatus());
        self::assertSame(0, $import->getProcessedCount());
    }
}
