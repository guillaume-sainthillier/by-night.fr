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
use App\Entity\Event;
use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PageFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use App\Repository\PageRepository;
use App\Repository\PlaceRepository;
use App\Repository\UserRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

/**
 * The sitemap queries are read page by page on a cursor: one row per page here, so that
 * every row is reached through the cursor of the row before it, none skipped nor repeated.
 */
final class SitemapQueriesTest extends AppKernelTestCase
{
    private const int ONE_ROW_PER_PAGE = 1;

    public function testEventsAreReadNewestFirstAcrossPages(): void
    {
        $place = $this->createPlace(CityFactory::toulouse()->create(), 'Le Bikini');
        $events = [$this->createEvent($place, 10), $this->createEvent($place, 5), $this->createEvent($place, 20)];

        $rows = self::getContainer()->get(EventRepository::class)->findAllSiteMap($this->today(), self::ONE_ROW_PER_PAGE);

        self::assertSame(
            array_reverse(array_map(static fn (Event $event): ?int => $event->getId(), $events)),
            array_column(iterator_to_array($rows, false), 'id'),
        );
    }

    public function testPlacesSharingASlugInACityMakeOneRowAndTheCursorMovesToTheNextCity(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $bordeaux = CityFactory::createOne(['name' => 'Bordeaux', 'country' => $toulouse->getCountry()]);
        $this->createEvent($this->createPlace($toulouse, 'Le Bikini'), 10);
        $this->createEvent($this->createPlace($toulouse, 'Le Bikini'), 10);
        $this->createEvent($this->createPlace($toulouse, 'Le Phare'), 10);
        $this->createEvent($this->createPlace($bordeaux, 'Le Bikini'), 10);

        $rows = self::getContainer()->get(PlaceRepository::class)->findAllSitemap($this->today(), self::ONE_ROW_PER_PAGE);

        self::assertSame([
            ['slug' => 'le-bikini', 'city_slug' => 'bordeaux'],
            ['slug' => 'le-bikini', 'city_slug' => 'toulouse'],
            ['slug' => 'le-phare', 'city_slug' => 'toulouse'],
        ], iterator_to_array($rows, false));
    }

    public function testTagsAreReadCityByCityFromBothRelations(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $bordeaux = CityFactory::createOne(['name' => 'Bordeaux', 'country' => $toulouse->getCountry()]);
        $jazz = TagFactory::createOne(['name' => 'Jazz']);
        $rock = TagFactory::createOne(['name' => 'Rock']);
        $blues = TagFactory::createOne(['name' => 'Blues']);
        EventFactory::createOne(['place' => $this->createPlace($toulouse, 'Le Bikini'), 'category' => $jazz, 'themes' => [$blues, $rock]] + $this->dates(10));
        EventFactory::createOne(['place' => $this->createPlace($toulouse, 'Le Phare'), 'category' => $rock, 'themes' => []] + $this->dates(10));
        EventFactory::createOne(['place' => $this->createPlace($bordeaux, 'Le Rocher'), 'category' => $jazz, 'themes' => [$blues]] + $this->dates(10));

        $rows = self::getContainer()->get(CityRepository::class)->findAllTagsSitemap(self::ONE_ROW_PER_PAGE);

        $byId = static fn (array ...$tags): array => array_map(static fn (array $tag): array => [$tag[0], $tag[1]->getId()], $tags);
        $expected = [
            // Categories, then themes, each ordered by city then tag id
            ...$byId(['bordeaux', $jazz], ['toulouse', $jazz], ['toulouse', $rock]),
            ...$byId(['bordeaux', $blues], ['toulouse', $rock], ['toulouse', $blues]),
        ];
        self::assertSame(
            $expected,
            array_map(static fn (array $tag): array => [$tag['citySlug'], $tag['tagId']], iterator_to_array($rows, false)),
        );
    }

    public function testCitiesUsersAndPagesAreAllReadAcrossPages(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $bordeaux = CityFactory::createOne(['name' => 'Bordeaux', 'country' => $toulouse->getCountry()]);
        $first = UserFactory::createOne();
        $second = UserFactory::createOne();
        UserEventFactory::createOne(['user' => $first, 'event' => $this->createEvent($this->createPlace($toulouse, 'Le Bikini'), 10)]);
        UserEventFactory::createOne(['user' => $second, 'event' => $this->createEvent($this->createPlace($bordeaux, 'Le Rocher'), 10)]);
        PageFactory::createOne(['title' => 'Mentions légales']);
        PageFactory::createOne(['title' => 'À propos']);

        $cities = self::getContainer()->get(CityRepository::class)->findAllSitemap($this->today(), self::ONE_ROW_PER_PAGE);
        $users = self::getContainer()->get(UserRepository::class)->findAllSitemap($this->today(), self::ONE_ROW_PER_PAGE);
        $pages = self::getContainer()->get(PageRepository::class)->findAllSitemap(self::ONE_ROW_PER_PAGE);

        self::assertSame(['bordeaux', 'toulouse'], array_column(iterator_to_array($cities, false), 'slug'));
        self::assertSame([$first->getId(), $second->getId()], array_column(iterator_to_array($users, false), 'id'));
        self::assertSame(['a-propos', 'mentions-legales'], array_column(iterator_to_array($pages, false), 'slug'));
    }

    private function createPlace(City $city, string $name): Place
    {
        return PlaceFactory::createOne(['name' => $name, 'city' => $city, 'country' => $city->getCountry()]);
    }

    private function createEvent(Place $place, int $daysFromToday): Event
    {
        return EventFactory::createOne(['place' => $place] + $this->dates($daysFromToday));
    }

    /**
     * @return array{startDate: DateTimeImmutable, endDate: DateTimeImmutable}
     */
    private function dates(int $daysFromToday): array
    {
        $date = $this->today()->modify(\sprintf('%+d days', $daysFromToday));

        return ['startDate' => $date, 'endDate' => $date];
    }

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }
}
