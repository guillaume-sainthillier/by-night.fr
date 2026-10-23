<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Location;

use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Tests\AppWebTestCase;
use DateTimeImmutable;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The map of the agenda (location/agenda/_map.html.twig): the events the page lists, placed by their venue, with no URL
 * of its own.
 */
final class AgendaMapTest extends AppWebTestCase
{
    use StubsAgendaSearch;

    public function testTheMapPlacesTheEventsOfThePageByTheirVenue(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry(), 'latitude' => 43.55, 'longitude' => 1.47]);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $concert = EventFactory::new()->withDates($tomorrow)->create(['name' => 'Concert "rock" <live>', 'place' => $bikini]);
        $this->stubAgendaSearch(events: [$concert]);

        $crawler = $client->request('GET', '/toulouse');

        self::assertResponseIsSuccessful();
        $events = $this->mapEvents($crawler);
        self::assertSame([[
            'name' => 'Concert "rock" <live>',
            'url' => \sprintf('/toulouse/soiree/%s--%d', $concert->getSlug(), $concert->getId()),
            'date' => $tomorrow->format('Y-m-d'),
            'venue' => $bikini->getId(),
            'venueName' => $concert->getPlaceName(),
            'lat' => 43.55,
            'lng' => 1.47,
        ]], $events);
        self::assertSelectorExists('#carte[hidden]');
        self::assertSelectorExists('button[data-map-toggle][aria-controls="carte"]');
        // No page of its own: the toggle is a button, and no link leads to the map
        self::assertCount(0, $crawler->filter('a[href*="carte"]'));
        self::assertSelectorExists('link[rel="canonical"][href="https://by-night.test/toulouse"]');
    }

    public function testTheMapLeavesOutTheEventsThatCannotBePlaced(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $unknown = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry(), 'latitude' => null, 'longitude' => null]);
        $notGeocoded = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry(), 'latitude' => 0.0, 'longitude' => 0.0]);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $this->stubAgendaSearch(events: [
            // No venue coordinates: the event's own are the venue's as it was imported
            EventFactory::new()->withDates($tomorrow)->create(['name' => 'Placed by itself', 'place' => $unknown, 'latitude' => 43.6, 'longitude' => 1.44]),
            EventFactory::new()->withDates($tomorrow)->create(['name' => 'Nowhere', 'place' => $unknown, 'latitude' => null, 'longitude' => null]),
            EventFactory::new()->withDates($tomorrow)->create(['name' => 'Never geocoded', 'place' => $notGeocoded, 'latitude' => 0.0, 'longitude' => 0.0]),
        ]);

        $crawler = $client->request('GET', '/toulouse');

        self::assertSame(['Placed by itself'], array_column($this->mapEvents($crawler), 'name'));
    }

    public function testAPageWithoutAnyEventToPlaceHasNoMap(): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();
        $this->stubAgendaSearch();

        $client->request('GET', '/toulouse');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-map-toggle]');
        self::assertSelectorNotExists('#carte');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapEvents(Crawler $crawler): array
    {
        $marker = $crawler->filter('[data-page=\'["agenda_map"]\']');
        self::assertCount(1, $marker);

        return json_decode((string) $marker->attr('data-page-params'), true, flags: \JSON_THROW_ON_ERROR)['events'];
    }
}
