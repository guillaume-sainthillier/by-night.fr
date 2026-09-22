<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EventSubscriber;

use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\EventSubscriber\SitemapSuscriber;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use LogicException;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Service\UrlContainerInterface;
use Presta\SitemapBundle\Sitemap\Url\Url;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;

/**
 * The sitemap only lists what is worth indexing now: Google ignores priorities, so a past
 * event or an empty listing must be left out rather than demoted.
 */
final class SitemapSuscriberTest extends AppKernelTestCase
{
    public function testEventsSectionOnlyListsEventsStillWorthIndexing(): void
    {
        $place = $this->createPlace(CityFactory::toulouse()->create(), 'Le Bikini');
        $upcoming = $this->createEvent($place, 10);
        $recentlyEnded = $this->createEvent($place, -10);
        $longEnded = $this->createEvent($place, -60);
        $duplicate = EventFactory::createOne(['place' => $place, 'duplicateOf' => $upcoming] + $this->dates(10));
        $draft = EventFactory::createOne(['place' => $place, 'draft' => true] + $this->dates(10));

        $paths = $this->collectSection('events');

        self::assertContains($this->eventPath($upcoming), $paths);
        self::assertContains($this->eventPath($recentlyEnded), $paths, 'A just-ended event stays listed during the grace period');
        self::assertNotContains($this->eventPath($longEnded), $paths);
        self::assertNotContains($this->eventPath($duplicate), $paths);
        self::assertNotContains($this->eventPath($draft), $paths);
    }

    public function testPlacesSectionListsPathBasedUrlsOfPlacesWithUpcomingEvents(): void
    {
        $city = CityFactory::toulouse()->create();
        $this->createEvent($this->createPlace($city, 'Le Bikini'), 10);
        $this->createEvent($this->createPlace($city, 'Le Phare'), -60);

        self::assertSame(['/toulouse/agenda/sortir-a/le-bikini'], $this->collectSection('places'));
    }

    public function testAgendaSectionListsEachCityWithUpcomingEventsOnce(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $bordeaux = CityFactory::createOne(['name' => 'Bordeaux', 'country' => $toulouse->getCountry()]);
        $this->createEvent($this->createPlace($toulouse, 'Le Bikini'), 10);
        $this->createEvent($this->createPlace($bordeaux, 'Le Rocher'), -60);

        self::assertSame(['/toulouse/', '/toulouse/agenda'], $this->collectSection('agenda'));
    }

    public function testCategoryPagesNeedAFullPageOfUpcomingEvents(): void
    {
        $place = $this->createPlace(CityFactory::toulouse()->create(), 'Le Bikini');
        EventFactory::createMany(SitemapSuscriber::CATEGORY_PAGES_MIN_EVENTS, ['place' => $place] + $this->dates(10));

        self::assertSame([
            '/toulouse/',
            '/toulouse/agenda',
            '/toulouse/agenda/sortir/concert',
            '/toulouse/agenda/sortir/etudiant',
            '/toulouse/agenda/sortir/famille',
            '/toulouse/agenda/sortir/spectacle',
            '/toulouse/agenda/sortir/exposition',
        ], $this->collectSection('agenda'));
    }

    public function testUsersSectionOnlyListsMembersWithUpcomingEventsInTheirCalendar(): void
    {
        $place = $this->createPlace(CityFactory::toulouse()->create(), 'Le Bikini');
        $active = UserFactory::createOne(['username' => 'active']);
        $retired = UserFactory::createOne(['username' => 'retired']);
        UserFactory::createOne(['username' => 'lurker']);
        UserEventFactory::createOne(['user' => $active, 'event' => $this->createEvent($place, 10)]);
        UserEventFactory::createOne(['user' => $retired, 'event' => $this->createEvent($place, -60)]);

        self::assertSame([\sprintf('/membres/active--%d', $active->getId())], $this->collectSection('users'));
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
        $date = new DateTimeImmutable('today')->modify(\sprintf('%+d days', $daysFromToday));

        return ['startDate' => $date, 'endDate' => $date];
    }

    private function eventPath(Event $event): string
    {
        return \sprintf('/%s/soiree/%s--%d', $event->getLocationSlug(), $event->getSlug(), $event->getId());
    }

    /**
     * @return list<string>
     */
    private function collectSection(string $section): array
    {
        $urlContainer = new class implements UrlContainerInterface {
            /** @var list<string> */
            public array $locs = [];

            public function addUrl(Url $url, string $section): void
            {
                if (!$url instanceof UrlConcrete) {
                    throw new LogicException('The sitemap only emits plain URLs');
                }

                $this->locs[] = $url->getLoc();
            }
        };

        self::getContainer()->get('event_dispatcher')->dispatch(
            new SitemapPopulateEvent($urlContainer, self::getContainer()->get('router'), $section)
        );

        return array_map(static function (string $loc): string {
            $query = parse_url($loc, \PHP_URL_QUERY);

            return parse_url($loc, \PHP_URL_PATH) . (\is_string($query) ? '?' . $query : '');
        }, $urlContainer->locs);
    }
}
