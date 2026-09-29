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
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Tests\Stats\CountsUpcomingEvents;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

use function Zenstruck\Foundry\Persistence\refresh;

/**
 * The page of a location: its agenda, introduced by its copy, its universes, its busiest cities and its neighbours.
 */
final class LocationPageTest extends WebTestCase
{
    use CountsUpcomingEvents;
    use StubsAgendaSearch;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLocationPages(): iterable
    {
        yield 'country' => ['/france', 'en France'];
        yield 'city' => ['/toulouse', 'à Toulouse'];
    }

    #[DataProvider('provideLocationPages')]
    public function testTheLocationPageCountsItsPublishedUpcomingEvents(string $url, string $atName): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $belgium = CountryFactory::belgium()->create();
        $brussels = CityFactory::createOne(['name' => 'Bruxelles', 'country' => $belgium]);
        $belgianPlace = PlaceFactory::createOne(['city' => $brussels, 'country' => $belgium]);
        $tomorrow = new DateTimeImmutable('tomorrow');

        $popular = EventFactory::new()->withDates($tomorrow)->create(['name' => 'Popular tomorrow', 'place' => $place]);
        EventFactory::new()->withDates($tomorrow)->create(['name' => 'Quiet tomorrow', 'place' => $place]);
        EventFactory::new()->withDates(new DateTimeImmutable('+2 days'))->create(['name' => 'In two days', 'place' => $place]);
        EventFactory::new()->withDates(new DateTimeImmutable('-10 days'))->create(['name' => 'Past', 'place' => $place]);
        EventFactory::new()->withDates($tomorrow)->create(['name' => 'Draft', 'place' => $place, 'draft' => true]);
        EventFactory::new()->withDates($tomorrow)->create(['name' => 'Duplicate', 'place' => $place, 'duplicateOf' => $popular]);
        EventFactory::new()->withDates($tomorrow)->create(['name' => 'Brussels', 'place' => $belgianPlace]);
        self::counter()->refresh();
        // The counter writes the counts by query: the page reads them from the entities already loaded
        $france = $toulouse->getCountry();
        refresh($toulouse);
        refresh($france);

        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.hero-location h1', 'Que faire ' . $atName . "\u{a0}? Concerts, expos & soirées");
        self::assertSelectorTextSame('#upcoming-count', '3 sorties à venir');
    }

    public function testACountryWithFewCitiesRanksNone(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        $monaco = CountryFactory::createOne(['id' => 'MC', 'name' => 'Monaco', 'displayName' => 'Monaco', 'atDisplayName' => 'à Monaco']);
        $city = CityFactory::createOne(['name' => 'Monaco', 'country' => $monaco]);
        $place = PlaceFactory::createOne(['name' => 'Grimaldi Forum', 'city' => $city, 'country' => $monaco]);
        EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['place' => $place]);
        self::counter()->refresh();

        $client->request('GET', '/monaco');

        self::assertResponseIsSuccessful();
        // Its busiest venues are the ones of the filters of its agenda
        self::assertSelectorNotExists('#cities');
    }

    public function testACountryWithSeveralCitiesShowsTheBusiest(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        $france = CountryFactory::france()->create();
        foreach (['Lyon' => 3, 'Nantes' => 1, 'Lille' => 2] as $name => $events) {
            $place = PlaceFactory::createOne(['city' => CityFactory::createOne(['name' => $name, 'country' => $france]), 'country' => $france]);
            EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->many($events)->create(['place' => $place]);
        }
        self::counter()->refresh();

        $crawler = $client->request('GET', '/france');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#cities h2', 'Les villes les plus animées');
        self::assertSame(['Lyon', 'Lille', 'Nantes'], $crawler->filter('#cities .card-title')->each(static fn ($title): string => trim($title->text())));
    }

    public function testACountryListsTheOtherCountriesWithTheCardsOfTheHomePage(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        $cities = ['FR' => ['Toulouse' => 1], 'BE' => ['Bruxelles' => 3, 'Liège' => 1], 'CH' => ['Genève' => 1]];
        foreach ([CountryFactory::france()->create(), CountryFactory::belgium()->create(), CountryFactory::switzerland()->create()] as $country) {
            foreach ($cities[$country->getId()] as $name => $events) {
                $place = PlaceFactory::createOne(['city' => CityFactory::createOne(['name' => $name, 'country' => $country]), 'country' => $country]);
                EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->many($events)->create(['place' => $place]);
            }
        }
        self::counter()->refresh();

        $crawler = $client->request('GET', '/france');

        self::assertResponseIsSuccessful();
        self::assertSame(['Belgique', 'Suisse'], $crawler->filter('#neighbours h3')->each(static fn ($title): string => trim($title->text())));
        // The busiest gets the big card, with its cities
        self::assertSelectorTextSame('#neighbours .card-lg h3', 'Belgique');
        self::assertSelectorTextContains('#neighbours .card-lg p', 'De Bruxelles à Liège');
    }

    #[DataProvider('provideLocationAgendas')]
    public function testTheUniversesLeadToTheAgendasOfTheLocation(string $url, string $agenda): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSame(
            [$agenda . '/sortir/concert', $agenda . '/sortir/spectacle', $agenda . '/sortir/exposition', $agenda . '/sortir/famille', $agenda . '/sortir/etudiant'],
            $crawler->filter('#univers a')->each(static fn ($link): ?string => $link->attr('href')),
        );
    }

    #[DataProvider('provideLocationPages')]
    public function testTheUniversesCountTheEventsToComeOfEachType(string $url, string $atName): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $tomorrow = new DateTimeImmutable('tomorrow');
        EventFactory::new()->withDates($tomorrow)->many(2)->create(['place' => $place, 'agendaTypes' => ['concert']]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $place, 'agendaTypes' => ['concert', 'family']]);
        self::counter()->refresh();
        // The counter writes the counts by query: the page reads them from the entities already loaded
        $france = $toulouse->getCountry();
        refresh($toulouse);
        refresh($france);

        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        // A type without any event names its page instead
        self::assertSame(
            ['3 concerts ' . $atName, 'Spectacles ' . $atName, 'Les expos ' . $atName, '1 sortie en famille ' . $atName, 'Soirées étudiantes ' . $atName],
            $crawler->filter('#univers .card-body > div:last-child > span')->each(static fn ($meta): string => trim($meta->text())),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLocationAgendas(): iterable
    {
        yield 'country' => ['/france', '/france/agenda'];
        yield 'city' => ['/toulouse', '/toulouse/agenda'];
    }

    #[DataProvider('provideLocationAgendas')]
    public function testTheFormerAgendaRedirectsToTheLocationPageWithItsFiltersAndItsPage(string $url, string $agenda): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        CityFactory::toulouse()->create();

        $client->request('GET', $agenda);
        self::assertResponseRedirects($url, Response::HTTP_MOVED_PERMANENTLY);

        $client->request('GET', $agenda . '/3?term=jazz&when=this_weekend');
        self::assertResponseRedirects($url . '/3?term=jazz&when=this_weekend', Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideLocationUrls(): iterable
    {
        yield 'country' => ['/france'];
        yield 'city' => ['/toulouse'];
    }

    #[DataProvider('provideLocationUrls')]
    public function testTheTrailingSlashRedirectsToTheLocationPage(string $url): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $client->request('GET', $url . '/?when=this_weekend');

        self::assertResponseRedirects('http://localhost' . $url . '?when=this_weekend', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testThePagesWhosePathEndsWithASlashAreNoLocations(): void
    {
        $client = self::createClient();

        // Without its slash, the search page is still found, not taken for a city: the router tries the static routes
        // first, and redirects to the one the slash alone sets apart
        $client->request('GET', '/recherche');

        self::assertResponseRedirects('http://localhost/recherche/', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testAFilterLeavesTheIntroductionOutForTheAgenda(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse?term=jazz');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.hero-location');
        self::assertSelectorNotExists('#univers');
        self::assertSelectorTextSame('h1', "Que faire à Toulouse\u{a0}?");
    }

    public function testTheLocationPageIsTheParentOfItsAgendas(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', '/toulouse/agenda/sortir/concert');

        self::assertResponseIsSuccessful();
        // The type page is the current one: the last link is the location, with no agenda in between
        $links = $crawler->filter('.breadcrumb a')->each(static fn ($link): string => (string) parse_url((string) $link->attr('href'), \PHP_URL_PATH));
        self::assertSame('/toulouse', end($links));
        self::assertNotContains('/toulouse/agenda', $links);
    }
}
