<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Transfer\Export;

use App\Entity\EventExport;
use App\Enum\EventExportScope;
use App\Enum\EventExportStatus;
use App\Enum\PersonalEventFilter;
use App\Enum\TransferFormat;
use App\Factory\CountryFactory;
use App\Factory\EventExportFactory;
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Tests\AppKernelTestCase;
use App\Tests\Transfer\TransferTestTrait;
use App\Transfer\Export\EventExporter;
use App\Transfer\Sheet\EventColumn;
use DateTimeImmutable;

use function Zenstruck\Foundry\Persistence\refresh;

final class EventExporterTest extends AppKernelTestCase
{
    use TransferTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTransfers();
    }

    public function testAnExportHoldsTheMembersEventsOfItsScopeOnly(): void
    {
        $tomorrow = new DateTimeImmutable('tomorrow');
        EventFactory::createOne(['user' => $this->user, 'name' => 'À venir', 'startDate' => $tomorrow, 'endDate' => $tomorrow]);
        EventFactory::createOne(['user' => $this->user, 'name' => 'Passé', 'startDate' => new DateTimeImmutable('-1 year'), 'endDate' => new DateTimeImmutable('-1 year')]);
        EventFactory::createOne(['name' => 'D’un autre membre', 'startDate' => $tomorrow, 'endDate' => $tomorrow]);

        $export = $this->export(EventExportScope::Upcoming, TransferFormat::Xlsx);

        self::assertSame(EventExportStatus::Completed, $export->getStatus());
        self::assertSame([1, 1], [$export->getTotalCount(), $export->getExportedCount()]);
        $sheet = $this->readStored((string) $export->getFilePath(), TransferFormat::Xlsx);
        self::assertSame(['À venir'], array_map(static fn ($row): string => $row->get(EventColumn::Name), $sheet['rows']));
    }

    public function testAnExportFromTheListHoldsTheEventsItShows(): void
    {
        EventFactory::createOne(['user' => $this->user, 'name' => 'Soirée jazz', 'draft' => true]);
        EventFactory::createOne(['user' => $this->user, 'name' => 'Soirée jazz en ligne', 'draft' => false]);
        EventFactory::createOne(['user' => $this->user, 'name' => 'Théâtre', 'draft' => true]);
        $export = EventExportFactory::createOne(['user' => $this->user, 'format' => TransferFormat::Csv, 'searchQuery' => 'jazz', 'statusFilter' => PersonalEventFilter::Hidden]);

        self::getContainer()->get(EventExporter::class)->export($export);
        refresh($export);

        self::assertSame(['Soirée jazz'], array_map(static fn ($row): string => $row->get(EventColumn::Name), $this->readStored((string) $export->getFilePath())['rows']));
    }

    public function testAnEventOfSeveralSessionsIsOneRowPerSession(): void
    {
        $event = EventFactory::createOne(['user' => $this->user, 'name' => 'Festival', 'externalReference' => 'FEST']);
        EventTimesheetFactory::createOne(['event' => $event, 'startAt' => new DateTimeImmutable('2027-03-12'), 'endAt' => new DateTimeImmutable('2027-03-12'), 'startTime' => new DateTimeImmutable('1970-01-01 20:00')]);
        EventTimesheetFactory::createOne(['event' => $event, 'startAt' => new DateTimeImmutable('2027-03-13'), 'endAt' => new DateTimeImmutable('2027-03-13')]);

        $rows = $this->readStored((string) $this->export()->getFilePath())['rows'];

        self::assertCount(2, $rows);
        self::assertSame(['FEST', 'FEST'], [$rows[0]->get(EventColumn::Reference), $rows[1]->get(EventColumn::Reference)]);
        self::assertSame(['Festival', ''], [$rows[0]->get(EventColumn::Name), $rows[1]->get(EventColumn::Name)]);
        self::assertSame(['12/03/2027', '13/03/2027'], [$rows[0]->get(EventColumn::StartDate), $rows[1]->get(EventColumn::StartDate)]);
        self::assertSame('20:00', $rows[0]->get(EventColumn::StartTime));
    }

    /**
     * What an export is for: changed in a spreadsheet, then imported back, the events are updated, not duplicated.
     */
    public function testAnExportChangedThenImportedUpdatesTheEvents(): void
    {
        $day = new DateTimeImmutable('+1 month');
        $event = EventFactory::createOne([
            'user' => $this->user, 'name' => 'Concert de jazz', 'description' => 'Une soirée de jazz au bord de l’eau.',
            'startDate' => $day, 'endDate' => $day, 'draft' => false, 'placeName' => 'La Guinguette', 'placeCity' => 'Toulouse',
            'placePostalCode' => '31000', 'placeCountry' => CountryFactory::find(['id' => 'FR']),
        ]);
        $csv = (string) $this->storage()->read((string) $this->export()->getFilePath());

        $import = $this->import(str_replace('Concert de jazz', 'Concert de jazz manouche', $csv));

        self::assertSame([0, 1, 0], [$import->getCreatedCount(), $import->getUpdatedCount(), $import->getFailedCount()], (string) json_encode($import->getErrors()));
        self::assertSame(1, EventFactory::count(['user' => $this->user]));
        self::assertSame('Concert de jazz manouche', EventFactory::find(['id' => $event->getId()])->getName());
    }

    public function testACalendarHoldsOneEntryPerEvent(): void
    {
        $day = new DateTimeImmutable('+1 month');
        EventFactory::createOne(['user' => $this->user, 'name' => 'Soirée jazz', 'startDate' => $day, 'endDate' => $day, 'startTime' => new DateTimeImmutable('1970-01-01 20:30'), 'endTime' => new DateTimeImmutable('1970-01-01 01:00')]);

        $ics = (string) $this->storage()->read((string) $this->export(format: TransferFormat::Ics)->getFilePath());

        self::assertStringContainsString('BEGIN:VCALENDAR', $ics);
        self::assertSame(1, substr_count($ics, 'BEGIN:VEVENT'));
        self::assertStringContainsString('SUMMARY:Soirée jazz', $ics);
        self::assertStringContainsString('DTSTART;TZID=Europe/Paris:' . $day->format('Ymd') . 'T203000', $ics);
        // Ending at 1:00, the night goes on into the next day
        self::assertStringContainsString('DTEND;TZID=Europe/Paris:' . $day->modify('+1 day')->format('Ymd') . 'T010000', $ics);
    }

    private function export(EventExportScope $scope = EventExportScope::All, TransferFormat $format = TransferFormat::Csv): EventExport
    {
        $export = EventExportFactory::createOne(['user' => $this->user, 'scope' => $scope, 'format' => $format]);

        self::getContainer()->get(EventExporter::class)->export($export);
        refresh($export);

        return $export;
    }
}
