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
use App\Enum\EventStatus;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PublisherFactory;
use App\Factory\UserEventFactory;
use App\Repository\EventImportRepository;
use App\Tests\AppKernelTestCase;
use App\Tests\Transfer\TransferTestTrait;
use App\Transfer\Import\EventImportRunner;
use DateTimeImmutable;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class EventImportRunnerTest extends AppKernelTestCase
{
    use TransferTestTrait;

    private const string HEADER = "Référence externe;ID;Nom;Description;Date de début;Heure de début;Heure de fin;Lieu;Code postal;Ville;Catégorie;Statut;Brouillon\n";

    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTransfers();
        $this->day = new DateTimeImmutable('+1 month')->format('d/m/Y');
    }

    public function testANewEventIsADraftOfTheMemberWhoGoesToIt(): void
    {
        $import = $this->import(self::HEADER . "JAZZ;;Soirée jazz;Deux soirs de jazz en plein air.;{$this->day};20:30;23:00;La Guinguette;31000;Toulouse;Concert;;\n");

        self::assertSame(EventImportStatus::Completed, $import->getStatus());
        self::assertSame([1, 1, 0, 0, 0], [$import->getProcessedCount(), $import->getCreatedCount(), $import->getUpdatedCount(), $import->getUnchangedCount(), $import->getFailedCount()]);

        $event = EventFactory::find(['externalReference' => 'JAZZ']);
        self::assertSame($this->user->getId(), $event->getUser()?->getId());
        self::assertSame($this->user->getId(), $event->getPublisher()?->getOwner()?->getId());
        self::assertTrue($event->isDraft());
        self::assertSame('Soirée jazz', $event->getName());
        self::assertSame('20:30', $event->getStartTime()?->format('H:i'));
        self::assertSame('Concert', $event->getCategory()?->getName());
        self::assertSame($event->getId(), $import->getOutcomes()[0]['id']);
        self::assertSame(1, UserEventFactory::count(['user' => $this->user, 'event' => $event, 'going' => true]));
    }

    /**
     * A reference names an event among those of the member's publisher: another publisher's is no match.
     */
    public function testAReferenceOfAnotherPublisherMakesANewEvent(): void
    {
        $publisher = PublisherFactory::createOne(['owner' => $this->user]);
        $other = EventFactory::createOne(['externalReference' => 'JAZZ', 'name' => 'Concert de jazz']);

        $import = $this->import(self::HEADER . "JAZZ;;Soirée jazz;Deux soirs de jazz en plein air.;{$this->day};;;La Guinguette;31000;Toulouse;;;\n");

        self::assertSame(1, $import->getCreatedCount());
        self::assertSame('Concert de jazz', EventFactory::find(['id' => $other->getId()])->getName());
        self::assertSame(1, EventFactory::count(['publisher' => $publisher, 'externalReference' => 'JAZZ']));
    }

    public function testTheBrouillonColumnCanPublishAtOnce(): void
    {
        $this->import(self::HEADER . "JAZZ;;Soirée jazz;Deux soirs de jazz en plein air.;{$this->day};;;La Guinguette;31000;Toulouse;;;non\n");

        self::assertFalse(EventFactory::find(['externalReference' => 'JAZZ'])->isDraft());
    }

    public function testRowsSharingAReferenceAreOneEventWithItsSessions(): void
    {
        $next = DateTimeImmutable::createFromFormat('!d/m/Y', $this->day)->modify('+7 days')->format('d/m/Y');
        $this->import(self::HEADER
            . "JAZZ;;Soirée jazz;Deux soirs de jazz en plein air.;{$this->day};20:30;23:00;La Guinguette;31000;Toulouse;;;\n"
            . "JAZZ;;;;{$next};21:00;23:30;;;;;;\n");

        $event = EventFactory::find(['externalReference' => 'JAZZ']);
        self::assertSame($this->day, $event->getStartDate()?->format('d/m/Y'));
        self::assertSame($next, $event->getEndDate()?->format('d/m/Y'));
        self::assertCount(2, $event->getOwnTimesheets());
        self::assertSame(1, EventFactory::count(['user' => $this->user]));
    }

    public function testImportingTheSameFileAgainChangesNothing(): void
    {
        $csv = self::HEADER . "JAZZ;;Soirée jazz;Deux soirs de jazz en plein air.;{$this->day};20:30;23:00;La Guinguette;31000;Toulouse;;;\n";
        $this->import($csv);

        $again = $this->import($csv);

        self::assertSame([0, 0, 1], [$again->getCreatedCount(), $again->getUpdatedCount(), $again->getUnchangedCount()]);
        self::assertSame(1, EventFactory::count(['user' => $this->user]));
    }

    public function testAChangedRowUpdatesTheEventOfItsReferenceAndKeepsItsDraftState(): void
    {
        $this->import(self::HEADER . "JAZZ;;Soirée jazz;Deux soirs de jazz en plein air.;{$this->day};;;La Guinguette;31000;Toulouse;;;non\n");

        $again = $this->import(self::HEADER . "jazz;;Soirée jazz manouche;Deux soirs de jazz en plein air.;{$this->day};;;La Guinguette;31000;Toulouse;;Annulé;\n");

        self::assertSame([0, 1], [$again->getCreatedCount(), $again->getUpdatedCount()]);
        $event = EventFactory::find(['user' => $this->user]);
        self::assertSame('Soirée jazz manouche', $event->getName());
        self::assertSame(EventStatus::Cancelled, $event->getStatus());
        self::assertFalse($event->isDraft());
    }

    public function testAnEventOfTheMemberIsUpdatedByItsId(): void
    {
        // In France: the file has no "Pays" column, the event keeps its country
        $event = EventFactory::createOne(['user' => $this->user, 'name' => 'Concert de jazz', 'draft' => false, 'placeCountry' => CountryFactory::find(['id' => 'FR'])]);

        $import = $this->import(self::HEADER . ";{$event->getId()};Concert de jazz manouche;Une soirée de jazz manouche.;{$this->day};;;Le Bikini;31520;Ramonville;;;\n");

        self::assertSame(1, $import->getUpdatedCount());
        $saved = EventFactory::find(['id' => $event->getId()]);
        self::assertSame('Concert de jazz manouche', $saved->getName());
        self::assertSame('Le Bikini', $saved->getPlaceName());
        self::assertFalse($saved->isDraft());
        self::assertSame(1, EventFactory::count(['user' => $this->user]));
    }

    public function testTheEventsTheMemberLeftOutAreSkipped(): void
    {
        $import = $this->check(self::HEADER
            . "JAZZ;;Soirée jazz;Deux soirs de jazz en plein air.;{$this->day};;;La Guinguette;31000;Toulouse;;;\n"
            . "BLUES;;Soirée blues;Une soirée de blues en plein air.;{$this->day};;;La Guinguette;31000;Toulouse;;;\n");
        $import->setSkippedRows([3]);
        save($import);
        self::getContainer()->get(EventImportRepository::class)->transition($import, [EventImportStatus::Checked], EventImportStatus::Importing);

        self::getContainer()->get(EventImportRunner::class)->run($import);
        refresh($import);

        self::assertSame([1, 1], [$import->getCreatedCount(), $import->getSkippedCount()]);
        self::assertSame('skipped', $import->getOutcomes()[1]['outcome']);
        self::assertSame(['JAZZ'], array_map(static fn ($event) => $event->getExternalReference(), EventFactory::findBy(['user' => $this->user])));
    }

    public function testAnEventDeletedSinceTheCheckIsNotImported(): void
    {
        $event = EventFactory::createOne(['user' => $this->user, 'placeCountry' => CountryFactory::find(['id' => 'FR'])]);
        $import = $this->check(self::HEADER . ";{$event->getId()};Concert;Une soirée de jazz manouche.;{$this->day};;;Le Bikini;31520;Ramonville;;;\n");
        delete($event);
        self::getContainer()->get(EventImportRepository::class)->transition($import, [EventImportStatus::Checked], EventImportStatus::Importing);

        self::getContainer()->get(EventImportRunner::class)->run($import);
        refresh($import);

        self::assertSame(EventImportStatus::Completed, $import->getStatus());
        self::assertSame(1, $import->getFailedCount());
    }
}
