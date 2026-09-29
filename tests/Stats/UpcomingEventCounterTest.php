<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Stats;

use App\App\Location;
use App\Entity\City;
use App\Entity\Country;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Factory\UpcomingCountFactory;
use App\Repository\EventRepository;
use App\Stats\UpcomingEventCounter;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class UpcomingEventCounterTest extends AppKernelTestCase
{
    use CountsUpcomingEvents;

    private UpcomingEventCounter $counter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->counter = self::counter();
    }

    public function testOnlyThePublishedEventsToComeAreCounted(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $france = $toulouse->getCountry();
        $lyon = CityFactory::createOne(['name' => 'Lyon', 'country' => $france]);
        $empty = CityFactory::createOne(['name' => 'Empty', 'country' => $france]);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $inToulouse = PlaceFactory::createOne(['city' => $toulouse, 'country' => $france]);
        $alsoInToulouse = PlaceFactory::createOne(['city' => $toulouse, 'country' => $france]);
        $inLyon = PlaceFactory::createOne(['city' => $lyon, 'country' => $france]);
        $published = EventFactory::new()->withDates($tomorrow)->many(2)->create(['place' => $inToulouse])[0];
        EventFactory::new()->withDates($tomorrow)->create(['place' => $alsoInToulouse]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon, 'draft' => true]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon, 'duplicateOf' => $published]);
        EventFactory::new()->withDates(new DateTimeImmutable('-10 days'))->create(['place' => $inLyon]);
        // Already started, not over yet
        EventFactory::new()->withDates(new DateTimeImmutable('-3 days'), $tomorrow)->create(['place' => $inLyon]);

        $this->counter->refresh();

        self::assertSame([2, 1, 2], array_map(static fn ($place): int => refresh($place)->getUpcomingEvents(), [$inToulouse, $alsoInToulouse, $inLyon]));
        self::assertSame([3, 2, 0], array_map(static fn ($city): int => refresh($city)->getUpcomingEvents(), [$toulouse, $lyon, $empty]));
        self::assertSame(5, refresh($france)->getUpcomingEvents());
    }

    public function testTheCountsGoBackToZeroWhenTheLastEventIsUnpublished(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $france = $toulouse->getCountry();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $france]);
        $event = EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['place' => $place]);
        $this->counter->refresh();
        self::assertSame(1, refresh($place)->getUpcomingEvents());

        $event->setDraft(true);
        save($event);
        $this->counter->refresh();

        self::assertSame(0, refresh($place)->getUpcomingEvents());
        self::assertSame(0, refresh($toulouse)->getUpcomingEvents());
        self::assertSame(0, refresh($france)->getUpcomingEvents());
    }

    public function testOnlyTheCountsThatChangedAreWritten(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $lyon = CityFactory::createOne(['name' => 'Lyon', 'country' => $toulouse->getCountry()]);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $inToulouse = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $inLyon = PlaceFactory::createOne(['city' => $lyon, 'country' => $toulouse->getCountry()]);
        $concert = TagFactory::createOne(['name' => 'Concert']);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inToulouse, 'category' => $concert]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon, 'category' => $concert]);

        // The category counts are rewritten each time: Toulouse, Lyon and France
        // The category and type counts are rewritten each time: Toulouse, Lyon and France
        self::assertSame(['countries' => 1, 'cities' => 2, 'places' => 2, 'categories' => 3, 'types' => 0], $this->counter->refresh());
        self::assertSame(['countries' => 0, 'cities' => 0, 'places' => 0, 'categories' => 3, 'types' => 0], $this->counter->refresh());

        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon, 'category' => $concert]);

        self::assertSame(['countries' => 1, 'cities' => 1, 'places' => 1, 'categories' => 3, 'types' => 0], $this->counter->refresh());
        self::assertSame(2, refresh($inLyon)->getUpcomingEvents());
        self::assertSame(1, refresh($inToulouse)->getUpcomingEvents());
    }

    public function testRecountingVenuesLeavesTheOtherZonesAsTheyWere(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $france = $toulouse->getCountry();
        $lyon = CityFactory::createOne(['name' => 'Lyon', 'country' => $france]);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $inToulouse = PlaceFactory::createOne(['city' => $toulouse, 'country' => $france]);
        $alsoInToulouse = PlaceFactory::createOne(['city' => $toulouse, 'country' => $france]);
        $inLyon = PlaceFactory::createOne(['city' => $lyon, 'country' => $france]);
        $concert = TagFactory::createOne(['name' => 'Concert']);
        $moved = EventFactory::new()->withDates($tomorrow)->create(['place' => $inToulouse, 'category' => $concert]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $alsoInToulouse, 'category' => $concert]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon, 'category' => $concert]);
        $paris = CityFactory::createOne(['name' => 'Paris', 'country' => $france]);
        $inParis = PlaceFactory::createOne(['city' => $paris, 'country' => $france]);
        $this->counter->refresh();

        // Moved from Toulouse to Lyon, one more in Lyon, and one in Paris whose venue is not recounted
        $moved->setPlace($inLyon);
        save($moved);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon, 'category' => $concert]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inParis, 'category' => null]);

        self::assertSame(
            ['countries' => 1, 'cities' => 2, 'places' => 2, 'categories' => 3, 'types' => 0],
            $this->counter->refreshPlaces([(int) $inToulouse->getId(), (int) $inLyon->getId()]),
        );
        self::assertSame([0, 1, 3, 0], array_map(static fn ($place): int => refresh($place)->getUpcomingEvents(), [$inToulouse, $alsoInToulouse, $inLyon, $inParis]));
        self::assertSame([1, 3, 0], array_map(static fn ($city): int => refresh($city)->getUpcomingEvents(), [$toulouse, $lyon, $paris]));
        self::assertSame(4, refresh($france)->getUpcomingEvents());
        $categoryCounts = [];
        foreach (UpcomingCountFactory::findBy(['tag' => $concert]) as $row) {
            $categoryCounts[(string) ($row->getCity() ?? $row->getCountry())?->getName()] = $row->getEvents();
        }

        ksort($categoryCounts);
        self::assertSame(['France' => 4, 'Lyon' => 3, 'Toulouse' => 1], $categoryCounts);
    }

    public function testRecountingNoVenueWritesNothing(): void
    {
        self::assertSame(['countries' => 0, 'cities' => 0, 'places' => 0, 'categories' => 0, 'types' => 0], $this->counter->refreshPlaces([]));
    }

    public function testEachTypeCountsTheEventsToComeItsPageLists(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $france = $toulouse->getCountry();
        $lyon = CityFactory::createOne(['name' => 'Lyon', 'country' => $france]);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $inToulouse = PlaceFactory::createOne(['city' => $toulouse, 'country' => $france]);
        $inLyon = PlaceFactory::createOne(['city' => $lyon, 'country' => $france]);
        EventFactory::new()->withDates($tomorrow)->many(2)->create(['place' => $inToulouse, 'agendaTypes' => ['concert']]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inToulouse, 'agendaTypes' => ['concert', 'family']]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inToulouse]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inToulouse, 'agendaTypes' => ['concert'], 'draft' => true]);
        EventFactory::new()->withDates(new DateTimeImmutable('-10 days'))->create(['place' => $inToulouse, 'agendaTypes' => ['concert']]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon, 'agendaTypes' => ['family']]);

        // Toulouse: concert and family, Lyon: family, France: concert and family
        self::assertSame(5, $this->counter->refresh()['types']);

        self::assertSame(['concert' => 3, 'family' => 1], $this->typeCounts($toulouse));
        self::assertSame(['family' => 1], $this->typeCounts($lyon));
        self::assertSame(['concert' => 3, 'family' => 2], $this->typeCounts($france));
    }

    public function testTheZonesAreCountedAPageAtATime(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $france = $toulouse->getCountry();
        $cities = [$toulouse, CityFactory::createOne(['name' => 'Lyon', 'country' => $france]), CityFactory::createOne(['name' => 'Nantes', 'country' => $france])];
        foreach ($cities as $index => $city) {
            $place = PlaceFactory::createOne(['city' => $city, 'country' => $france]);
            EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->many($index + 1)->create(['place' => $place, 'agendaTypes' => ['show']]);
        }

        // One city per page: every page is read after the one before
        $counter = new UpcomingEventCounter(
            self::getContainer()->get(EntityManagerInterface::class),
            self::getContainer()->get(EventRepository::class),
            zonesPerPage: 1,
        );

        self::assertSame(4, $counter->refresh()['types']);
        self::assertSame([['show' => 1], ['show' => 2], ['show' => 3], ['show' => 6]], [...array_map($this->typeCounts(...), $cities), $this->typeCounts($france)]);
    }

    public function testTheCountsOfAZoneLeftWithoutEventsAreRemoved(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $france = $toulouse->getCountry();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $france]);
        $event = EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['place' => $place, 'agendaTypes' => ['concert'], 'category' => TagFactory::createOne()]);
        $this->counter->refresh();
        self::assertSame(4, UpcomingCountFactory::count());

        // Over: no page holds Toulouse or France anymore
        $event->setDraft(true);
        save($event);

        self::assertSame(['countries' => 1, 'cities' => 1, 'places' => 1, 'categories' => 0, 'types' => 0], $this->counter->refresh());
        self::assertSame(0, UpcomingCountFactory::count());
    }

    public function testTheTypesStoredAfterTheCountAreRecountedAlone(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $france = $toulouse->getCountry();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $france]);
        $event = EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['place' => $place, 'category' => TagFactory::createOne()]);
        $this->counter->refresh();
        self::assertSame([], $this->typeCounts($toulouse));

        // What app:events:classify-agenda-types stores after the midnight count
        $event->setAgendaTypes(['exhibition']);
        save($event);

        self::assertSame(2, $this->counter->refreshAgendaTypes());
        self::assertSame(['exhibition' => 1], $this->typeCounts($toulouse));
        self::assertSame(['exhibition' => 1], $this->typeCounts($france));
        self::assertSame(2, UpcomingCountFactory::count(['agendaType' => null]), 'The category counts are left as they are');
    }

    public function testTheTypeCountsOfARecountedVenueAreRewritten(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $france = $toulouse->getCountry();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $france]);
        $event = EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['place' => $place, 'agendaTypes' => ['student']]);
        $this->counter->refresh();
        self::assertSame(['student' => 1], $this->typeCounts($toulouse));

        $event->setDraft(true);
        save($event);

        self::assertSame(0, $this->counter->refreshPlaces([(int) $place->getId()])['types']);
        self::assertSame([], $this->typeCounts($toulouse));
        self::assertSame([], $this->typeCounts($france));
    }

    /**
     * @return array<string, int>
     */
    private function typeCounts(City|Country $zone): array
    {
        $location = $zone instanceof City ? new Location()->setCity($zone) : new Location()->setCountry($zone);

        return self::getContainer()->get(EventRepository::class)->findUpcomingAgendaTypes($location);
    }
}
