<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Location;

use App\Entity\Event;
use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\PlaceLegacySlugFactory;
use App\Tests\AppWebTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

/**
 * A venue's events outside its agenda: the calendar its public subscribes to, the widget its website frames.
 */
final class PlaceFeedControllerTest extends AppWebTestCase
{
    use StubsAgendaSearch;

    public function testTheVenuesAgendaOffersItsCalendar(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        $this->createBikini();

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('aside', "L'agenda du Bikini dans votre calendrier");
        self::assertSelectorExists('aside a[href="webcal://by-night.test/toulouse/agenda/sortir-a/le-bikini/calendrier.ics"]');
    }

    public function testTheCalendarListsTheVenuesEventsOnline(): void
    {
        $client = self::createClient();
        $bikini = $this->createBikini();
        $upcoming = $this->createEvent($bikini, '+10 days', ['name' => 'Concert de jazz']);
        $this->createEvent($bikini, '+10 days', ['name' => 'Brouillon', 'draft' => true]);
        $this->createEvent($bikini, '+10 days', ['name' => 'Doublon', 'duplicateOf' => $upcoming]);
        $this->createEvent(PlaceFactory::createOne(['city' => $bikini->getCity(), 'country' => $bikini->getCountry()]), '+10 days', ['name' => 'Concert ailleurs']);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini/calendrier.ics');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Tag', 'place-' . $bikini->getId());
        $calendar = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('X-WR-CALNAME:Le Bikini · By Night', $calendar);
        self::assertStringContainsString('SUMMARY:Concert de jazz', $calendar);
        self::assertStringNotContainsString('Brouillon', $calendar);
        self::assertStringNotContainsString('Doublon', $calendar);
        self::assertStringNotContainsString('Concert ailleurs', $calendar);
    }

    public function testTheWidgetListsTheVenuesNextEvents(): void
    {
        $client = self::createClient();
        $bikini = $this->createBikini();
        $this->createEvent($bikini, '+3 days', ['name' => 'Concert de jazz']);
        $this->createEvent($bikini, '-3 days', ['name' => 'Déjà passé']);

        $crawler = $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini/widget');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Security-Policy', 'frame-ancestors *');
        self::assertSame(['Concert de jazz'], $crawler->filter('.list-group-item strong')->each(static fn ($name): string => trim($name->text())));
        self::assertSelectorExists('a[href="https://by-night.test/toulouse/agenda/sortir-a/le-bikini?utm_source=widget&utm_medium=embed&utm_campaign=place-le-bikini&utm_content=agenda"]');
        self::assertSame([], $client->getResponse()->headers->getCookies());
    }

    public function testTheFeedsOfAMergedVenueLeadToTheVenueThatTookItsEvents(): void
    {
        $client = self::createClient();
        $bikini = $this->createBikini();
        PlaceLegacySlugFactory::createOne(['place' => $bikini, 'slug' => 'le-bikini-1', 'city' => $bikini->getCity(), 'country' => $bikini->getCountry()]);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini-1/calendrier.ics');

        self::assertResponseRedirects('/toulouse/agenda/sortir-a/le-bikini/calendrier.ics', Response::HTTP_MOVED_PERMANENTLY);
    }

    private function createBikini(): Place
    {
        $toulouse = CityFactory::toulouse()->create();

        return PlaceFactory::createOne(['name' => 'Le Bikini', 'slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createEvent(Place $place, string $date, array $attributes): Event
    {
        return EventFactory::new()->withDates(new DateTimeImmutable($date))->create(['place' => $place, ...$attributes]);
    }
}
