<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Repository;

use App\App\Location;
use App\Entity\Event;
use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;
use App\Tests\Stats\CountsUpcomingEvents;
use DateTimeImmutable;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;

use function Zenstruck\Foundry\Persistence\save;

/**
 * The blocks of the home and location portals: highlights, venues, cities and counts.
 */
final class EventRepositoryPortalTest extends AppKernelTestCase
{
    use CountsUpcomingEvents;

    private EventRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(EventRepository::class);
    }

    public function testTheHighlightsAreTheEventsOfTheWeekWithAPictureTheMostFollowedFirst(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $elsewhere = PlaceFactory::createOne();
        $tomorrow = new DateTimeImmutable('tomorrow');
        $withPicture = ['image' => $this->picture('poster.jpg')];

        $this->event('Popular', $tomorrow, $place, ['participations' => 50] + $withPicture);
        $this->event('Quiet', $tomorrow, $place, ['participations' => 5] + $withPicture);
        $this->event('System picture', $tomorrow, $place, ['participations' => 1, 'imageSystem' => $this->picture('fetched.jpg')]);
        $this->event('No picture', $tomorrow, $place, ['participations' => 500]);
        $this->event('Next month', new DateTimeImmutable('+1 month'), $place, ['participations' => 500] + $withPicture);
        $this->event('Draft', $tomorrow, $place, ['participations' => 500, 'draft' => true] + $withPicture);
        $this->event('Elsewhere', $tomorrow, $elsewhere, ['participations' => 500] + $withPicture);

        $highlights = $this->repository->findHighlights(new Location()->setCity($toulouse), 10);

        self::assertSame(['Popular', 'Quiet', 'System picture'], $this->names($highlights));
    }

    public function testTheCitiesAroundLeaveOutTheCityAndTheFarAwayOnes(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $france = $toulouse->getCountry();
        $tomorrow = new DateTimeImmutable('tomorrow');
        $cities = [
            'Toulouse' => $toulouse,
            'Colomiers' => CityFactory::createOne(['name' => 'Colomiers', 'latitude' => 43.61, 'longitude' => 1.33, 'country' => $france]),
            'Albi' => CityFactory::createOne(['name' => 'Albi', 'latitude' => 43.93, 'longitude' => 2.15, 'country' => $france]),
            'Lyon' => CityFactory::createOne(['name' => 'Lyon', 'latitude' => 45.76, 'longitude' => 4.84, 'country' => $france]),
            'Quiet' => CityFactory::createOne(['name' => 'Quiet', 'latitude' => 43.5, 'longitude' => 1.4, 'country' => $france]),
        ];
        foreach (['Toulouse' => 3, 'Colomiers' => 1, 'Albi' => 2, 'Lyon' => 5] as $name => $events) {
            $place = PlaceFactory::createOne(['city' => $cities[$name], 'country' => $france]);
            EventFactory::new()->withDates($tomorrow)->many($events)->create(['place' => $place]);
        }

        self::counter()->refresh();

        $around = $this->repository->findUpcomingCitiesAround($toulouse, 5);

        self::assertSame(
            [['Albi', 2], ['Colomiers', 1]],
            array_map(static fn (array $row): array => [$row[0]->getName(), (int) $row['events']], $around),
        );
    }

    public function testTheTotalsAddUpTheEventsToComeAndTheCitiesAndVenuesThatHostThem(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $lyon = CityFactory::createOne(['name' => 'Lyon', 'country' => $toulouse->getCountry()]);
        CityFactory::createOne(['name' => 'Empty', 'country' => $toulouse->getCountry()]);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $inToulouse = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $alsoInToulouse = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $inLyon = PlaceFactory::createOne(['city' => $lyon, 'country' => $toulouse->getCountry()]);
        EventFactory::new()->withDates($tomorrow)->many(2)->create(['place' => $inToulouse]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $alsoInToulouse]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon, 'draft' => true]);
        self::counter()->refresh();

        self::assertSame(['events' => 3, 'cities' => 1, 'places' => 2], $this->repository->getUpcomingTotals());
    }

    public function testTheCategoriesOfACountryAreRankedByTheirEventsToCome(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $abroad = PlaceFactory::createOne(['country' => CountryFactory::belgium()]);
        $concert = TagFactory::createOne(['name' => 'Concert']);
        $theatre = TagFactory::createOne(['name' => 'Théâtre']);
        $tomorrow = new DateTimeImmutable('tomorrow');
        EventFactory::new()->withDates($tomorrow)->create(['place' => $place, 'category' => $concert]);
        EventFactory::new()->withDates($tomorrow)->many(2)->create(['place' => $place, 'category' => $theatre]);
        EventFactory::new()->withDates($tomorrow)->many(3)->create(['place' => $abroad, 'category' => $concert]);
        self::counter()->refresh();

        $categories = $this->repository->findUpcomingCategories(new Location()->setCountry($toulouse->getCountry()), 5);

        self::assertSame(
            [['Théâtre', 2], ['Concert', 1]],
            array_map(static fn (array $row): array => [$row[0]->getName(), (int) $row['events']], $categories),
        );
    }

    public function testTheCategoriesOfACityLeaveOutThePastDraftsDuplicatesAndOtherCities(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $lyon = PlaceFactory::createOne(['city' => CityFactory::createOne(['name' => 'Lyon', 'country' => $toulouse->getCountry()]), 'country' => $toulouse->getCountry()]);
        $concert = TagFactory::createOne(['name' => 'Concert']);
        $theatre = TagFactory::createOne(['name' => 'Théâtre']);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $original = EventFactory::new()->withDates($tomorrow)->create(['place' => $place, 'category' => $concert]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $place, 'category' => $theatre]);
        EventFactory::new()->withDates(new DateTimeImmutable('-1 week'))->many(2)->create(['place' => $place, 'category' => $theatre]);
        EventFactory::new()->withDates($tomorrow)->many(2)->create(['place' => $place, 'category' => $theatre, 'draft' => true]);
        EventFactory::new()->withDates($tomorrow)->many(2)->create(['place' => $place, 'category' => $theatre, 'duplicateOf' => $original]);
        EventFactory::new()->withDates($tomorrow)->many(3)->create(['place' => $lyon, 'category' => $theatre]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $place, 'category' => null]);
        self::counter()->refresh();

        $categories = $this->repository->findUpcomingCategories(new Location()->setCity($toulouse), 5);

        self::assertSame(
            [['Concert', 1], ['Théâtre', 1]],
            array_map(static fn (array $row): array => [$row[0]->getName(), (int) $row['events']], $categories),
        );
    }

    public function testTheNextCountReplacesTheCategoriesOfTheLastOne(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $concert = TagFactory::createOne(['name' => 'Concert']);
        $event = EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['place' => $place, 'category' => $concert]);
        self::counter()->refresh();

        // Unpublished since: its category has no event to come left
        $event->setDraft(true);
        save($event);
        self::counter()->refresh();

        self::assertSame([], $this->repository->findUpcomingCategories(new Location()->setCity($toulouse), 5));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function event(string $name, DateTimeImmutable $day, Place $place, array $attributes): void
    {
        EventFactory::new()->withDates($day)->create(['name' => $name, 'place' => $place] + $attributes);
    }

    private function picture(string $name): EmbeddedFile
    {
        $picture = new EmbeddedFile();
        $picture->setName($name);

        return $picture;
    }

    /**
     * @param Event[] $events
     *
     * @return list<string|null>
     */
    private function names(array $events): array
    {
        return array_map(static fn (Event $event): ?string => $event->getName(), $events);
    }
}
