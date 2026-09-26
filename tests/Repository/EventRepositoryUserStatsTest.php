<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Repository;

use App\Entity\City;
use App\Entity\Place;
use App\Entity\Tag;
use App\Entity\User;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

/**
 * The statistics of a member's profile (/membres/{slug}--{id}) count the published events of their calendar.
 */
final class EventRepositoryUserStatsTest extends AppKernelTestCase
{
    public function testTheEventsOfACalendarAreCountedByTheDayTheyStart(): void
    {
        $member = UserFactory::createOne();
        $this->addToCalendar($member, '2024-05-03');
        $this->addToCalendar($member, '2024-05-03');
        $this->addToCalendar($member, '2024-05-01', '2024-05-04');
        $this->addToCalendar($member, '2024-06-01', draft: true);
        // Someone else's calendar
        $this->addToCalendar(UserFactory::createOne(), '2024-05-03');

        // The event from May 1st to 4th counts on the 1st
        self::assertSame(['2024-05-01' => 1, '2024-05-03' => 2], $this->repository()->countUserEventsByDay($member));
        self::assertSame(3, $this->repository()->getUserFavoriteEventsCount($member));
    }

    public function testTheCitiesComeBusiestFirstWithTheYearsOfTheirEvents(): void
    {
        $member = UserFactory::createOne();
        $toulouse = CityFactory::toulouse()->create();
        $bordeaux = CityFactory::createOne(['name' => 'Bordeaux', 'country' => $toulouse->getCountry()]);
        $this->addToCalendar($member, '2019-03-01', place: $this->placeIn($bordeaux));
        $this->addToCalendar($member, '2014-06-11', place: $this->placeIn($toulouse));
        $this->addToCalendar($member, '2026-09-01', place: $this->placeIn($toulouse));

        self::assertSame([
            ['name' => 'Toulouse', 'slug' => $toulouse->getSlug(), 'events' => 2, 'firstYear' => 2014, 'lastYear' => 2026],
            ['name' => 'Bordeaux', 'slug' => $bordeaux->getSlug(), 'events' => 1, 'firstYear' => 2019, 'lastYear' => 2019],
        ], $this->repository()->findUserCities($member));
    }

    public function testTheVenuesComeBusiestFirstWithTheAgendaTheyLinkTo(): void
    {
        $member = UserFactory::createOne();
        $toulouse = CityFactory::toulouse()->create();
        $bikini = $this->placeIn($toulouse, 'Le Bikini');
        $countryside = PlaceFactory::createOne(['name' => 'Un champ', 'city' => null, 'country' => $toulouse->getCountry()]);
        $this->addToCalendar($member, '2024-05-03', place: $countryside);
        $this->addToCalendar($member, '2024-05-03', place: $bikini);
        $this->addToCalendar($member, '2024-05-04', place: $bikini);

        $places = $this->repository()->findUserPlaces($member);

        self::assertSame([
            ['name' => 'Le Bikini', 'slug' => $bikini->getSlug(), 'locationSlug' => $toulouse->getSlug(), 'cityName' => 'Toulouse', 'events' => 2],
            ['name' => 'Un champ', 'slug' => $countryside->getSlug(), 'locationSlug' => $toulouse->getCountry()->getSlug(), 'cityName' => null, 'events' => 1],
        ], $places);
        self::assertCount(1, $this->repository()->findUserPlaces($member, 1));
        self::assertSame(2, $this->repository()->countUserPlaces($member));
    }

    public function testTheCategoriesComeBusiestFirstWithTheThemesTheMemberComesBackTo(): void
    {
        $member = UserFactory::createOne();
        $concert = TagFactory::createOne(['name' => 'Concert']);
        $theatre = TagFactory::createOne(['name' => 'Théâtre']);
        $rock = TagFactory::createOne(['name' => 'Rock']);
        $metal = TagFactory::createOne(['name' => 'Metal']);
        $this->addToCalendar($member, '2019-03-01', category: $concert, themes: [$rock, $metal, $concert]);
        $this->addToCalendar($member, '2024-05-03', category: $concert, themes: [$rock, $concert]);
        $this->addToCalendar($member, '2024-05-04', category: $theatre, themes: [$rock]);
        $this->addToCalendar($member, '2024-05-05');

        $categories = $this->repository()->findUserCategories($member);

        self::assertSame([
            ['id' => $concert->getId(), 'name' => 'Concert', 'events' => 2, 'firstYear' => 2019, 'lastYear' => 2024],
            ['id' => $theatre->getId(), 'name' => 'Théâtre', 'events' => 1, 'firstYear' => 2024, 'lastYear' => 2024],
        ], $categories);
        // Metal, and Rock under Théâtre, come once: noise. Concert repeats the category.
        self::assertSame(
            [$concert->getId() => ['Rock']],
            $this->repository()->findUserThemesByCategory($member, [$concert->getId(), $theatre->getId()]),
        );
    }

    public function testTheHabitsCountTheEventsAddedLongBeforeAndTheFreeOnes(): void
    {
        $member = UserFactory::createOne();
        $calendar = [
            // Added 14 days ahead, free
            ['2024-05-15', '2024-05-01 23:00', 'Gratuit'],
            // Added 13 days ahead, free
            ['2024-05-15', '2024-05-02 08:00', 'Entrée libre - Réservation conseillée'],
            // Added 30 days ahead, free for the young ones only
            ['2024-05-31', '2024-05-01 10:00', '4 € - gratuit pour les moins de 18 ans'],
            // Added on the day, no price
            ['2024-06-01', '2024-06-01 10:00', null],
        ];
        foreach ($calendar as [$startDate, $addedAt, $prices]) {
            $event = EventFactory::new()->withDates(new DateTimeImmutable($startDate))->create(['prices' => $prices]);
            UserEventFactory::createOne(['user' => $member, 'event' => $event, 'createdAt' => new DateTimeImmutable($addedAt)]);
        }

        self::assertSame(['addedAhead' => 2, 'free' => 2], $this->repository()->countUserCalendarHabits($member, 14));
        self::assertSame(['addedAhead' => 0, 'free' => 0], $this->repository()->countUserCalendarHabits(UserFactory::createOne(), 14));
    }

    public function testAMemberWithoutEventsHasNoStatistics(): void
    {
        $member = UserFactory::createOne();

        self::assertSame([], $this->repository()->countUserEventsByDay($member));
        self::assertSame([], $this->repository()->findUserCities($member));
        self::assertSame([], $this->repository()->findUserPlaces($member));
        self::assertSame(0, $this->repository()->countUserPlaces($member));
    }

    /**
     * @param list<Tag> $themes
     */
    private function addToCalendar(User $member, string $startDate, ?string $endDate = null, bool $draft = false, ?Place $place = null, ?Tag $category = null, array $themes = []): void
    {
        $event = EventFactory::new()
            ->withDates(new DateTimeImmutable($startDate), null === $endDate ? null : new DateTimeImmutable($endDate))
            ->create(['draft' => $draft, 'category' => $category, 'themes' => $themes] + (null === $place ? [] : ['place' => $place]));

        UserEventFactory::createOne(['user' => $member, 'event' => $event]);
    }

    private function placeIn(City $city, ?string $name = null): Place
    {
        return PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()] + (null === $name ? [] : ['name' => $name]));
    }

    private function repository(): EventRepository
    {
        return self::getContainer()->get(EventRepository::class);
    }
}
