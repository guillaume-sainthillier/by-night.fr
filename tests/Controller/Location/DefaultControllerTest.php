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
use Symfony\Component\DomCrawler\Crawler;

use function Zenstruck\Foundry\Persistence\refresh;

final class DefaultControllerTest extends WebTestCase
{
    use CountsUpcomingEvents;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLocationPages(): iterable
    {
        yield 'country' => ['/c--france/', 'en France'];
        yield 'city' => ['/toulouse/', 'à Toulouse'];
    }

    #[DataProvider('provideLocationPages')]
    public function testTheLocationPageCountsItsPublishedUpcomingEvents(string $url, string $atName): void
    {
        $client = self::createClient();
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

    public function testACountryWithFewCitiesShowsItsVenues(): void
    {
        $client = self::createClient();
        $monaco = CountryFactory::createOne(['id' => 'MC', 'name' => 'Monaco', 'displayName' => 'Monaco', 'atDisplayName' => 'à Monaco']);
        $city = CityFactory::createOne(['name' => 'Monaco', 'country' => $monaco]);
        $place = PlaceFactory::createOne(['name' => 'Grimaldi Forum', 'city' => $city, 'country' => $monaco]);
        EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['place' => $place]);
        self::counter()->refresh();

        $client->request('GET', '/c--monaco/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#venues h2', 'Les lieux incontournables');
        self::assertSelectorTextSame('#venues .card-title', 'Grimaldi Forum');
    }

    public function testACountryWithSeveralCitiesShowsTheBusiest(): void
    {
        $client = self::createClient();
        $france = CountryFactory::france()->create();
        foreach (['Lyon' => 3, 'Nantes' => 1, 'Lille' => 2] as $name => $events) {
            $place = PlaceFactory::createOne(['city' => CityFactory::createOne(['name' => $name, 'country' => $france]), 'country' => $france]);
            EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->many($events)->create(['place' => $place]);
        }
        self::counter()->refresh();

        $crawler = $client->request('GET', '/c--france/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#venues h2', 'Les villes les plus animées');
        self::assertSame(['Lyon', 'Lille', 'Nantes'], $crawler->filter('#venues .card-title')->each(static fn ($title): string => trim($title->text())));
    }

    public function testACountryListsTheOtherCountriesWithTheCardsOfTheHomePage(): void
    {
        $client = self::createClient();
        $cities = ['FR' => ['Toulouse' => 1], 'BE' => ['Bruxelles' => 3, 'Liège' => 1], 'CH' => ['Genève' => 1]];
        foreach ([CountryFactory::france()->create(), CountryFactory::belgium()->create(), CountryFactory::switzerland()->create()] as $country) {
            foreach ($cities[$country->getId()] as $name => $events) {
                $place = PlaceFactory::createOne(['city' => CityFactory::createOne(['name' => $name, 'country' => $country]), 'country' => $country]);
                EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->many($events)->create(['place' => $place]);
            }
        }
        self::counter()->refresh();

        $crawler = $client->request('GET', '/c--france/');

        self::assertResponseIsSuccessful();
        self::assertSame(['Belgique', 'Suisse'], $crawler->filter('#neighbours h3')->each(static fn ($title): string => trim($title->text())));
        // The busiest gets the big card, with its cities
        self::assertSelectorTextSame('#neighbours .card-lg h3', 'Belgique');
        self::assertSelectorTextContains('#neighbours .card-lg p', 'De Bruxelles à Liège');
    }

    public function testTheLastStepOfTheBreadcrumbIsTheCityPageItself(): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', '/toulouse/');

        self::assertResponseIsSuccessful();
        // The breadcrumb's JSON-LD, which gives the URL of every step, the last one included
        $breadcrumb = $crawler->filter('script[type="application/ld+json"]')
            ->each(static fn (Crawler $script): array => json_decode($script->text(), true, flags: \JSON_THROW_ON_ERROR));
        $breadcrumb = array_values(array_filter($breadcrumb, static fn (array $jsonLd): bool => 'BreadcrumbList' === ($jsonLd['@type'] ?? null)));
        self::assertCount(1, $breadcrumb);
        $last = end($breadcrumb[0]['itemListElement']);
        self::assertSame(['Sortir à Toulouse', 'http://localhost/toulouse'], [$last['name'], $last['item']]);
    }

    #[DataProvider('provideLocationAgendas')]
    public function testTheUniversesLeadToTheAgendasOfTheLocation(string $url, string $agenda): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSame(
            [$agenda . '/sortir/concert', $agenda . '/sortir/spectacle', $agenda . '/sortir/exposition', $agenda . '/sortir/famille', $agenda . '/sortir/etudiant'],
            $crawler->filter('#univers a')->each(static fn ($link): ?string => $link->attr('href')),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLocationAgendas(): iterable
    {
        yield 'country' => ['/c--france/', '/c--france/agenda'];
        yield 'city' => ['/toulouse/', '/toulouse/agenda'];
    }

    #[DataProvider('provideLocationAgendas')]
    public function testTheSearchIsSentByGetToTheFiltersOfTheAgendaOfTheLocation(string $url, string $agenda): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', $url);

        self::assertSelectorExists('input[name="when"][value="anytime"]:checked');
        $form = $crawler->selectButton('Explorer')->form(['term' => 'jazz', 'when' => 'this_weekend']);
        self::assertSame('GET', $form->getMethod());
        self::assertSame('http://localhost' . $agenda . '?term=jazz&when=this_weekend', $form->getUri());
    }
}
