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
use App\SEO\DatePageIndexingPolicy;
use App\Tests\AppWebTestCase;
use App\Tests\Stats\CountsUpcomingEvents;
use DateTimeImmutable;
use IntlDateFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

use function Zenstruck\Foundry\Persistence\refresh;

/**
 * The date pages of a location ("/toulouse/ce-week-end"): the agenda of a date shortcut people search for by name,
 * indexed in busy locations only (DatePageIndexingPolicy).
 */
final class DatePageTest extends AppWebTestCase
{
    use CountsUpcomingEvents;
    use StubsAgendaSearch;

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideDatePages(): iterable
    {
        yield 'tonight in a city' => ['/toulouse/ce-soir', "Aujourd'hui", "Que faire ce soir à Toulouse\u{a0}?"];
        yield 'this weekend in a city' => ['/toulouse/ce-week-end', 'Ce week-end', "Que faire ce week-end à Toulouse\u{a0}?"];
        yield 'this weekend in a country' => ['/france/ce-week-end', 'Ce week-end', "Que faire ce week-end en France\u{a0}?"];
    }

    #[DataProvider('provideDatePages')]
    public function testADatePageIsTheAgendaOfItsShortcut(string $url, string $chip, string $title): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch(hits: 3);
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', $title);
        self::assertStringStartsWith(str_replace("\u{a0}", ' ', $title), $crawler->filter('title')->text());
        self::assertSelectorTextContains('#agenda-dates .btn-chip.active', $chip);
        self::assertSame('https://by-night.test' . $url, $crawler->filter('link[rel="canonical"]')->attr('href'));
        // The form searches the page again, its shortcut with it
        self::assertSame($url, parse_url((string) $crawler->filter('#search-form')->attr('action'), \PHP_URL_PATH));
        self::assertSelectorExists('#search-form input[type="hidden"][name="when"]:not([value=""])');
    }

    public function testTheDaysOfTheWeekendAreSaid(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch(hits: 3);
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse/ce-week-end');

        $friday = new DateTimeImmutable('friday this week');
        $from = max($friday, new DateTimeImmutable('today'));
        self::assertSelectorTextContains('.agenda-header-card p', '3 sorties à Toulouse du ' . strtolower(new IntlDateFormatter('fr', pattern: 'EEEE d')->format($from)));
    }

    public function testAQuietLocationLinksItsDatePagesWithoutFollowingThem(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch(hits: 3);
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', '/toulouse');

        self::assertSame('nofollow', $crawler->filter('#agenda-dates a[href="/toulouse/ce-week-end"]')->attr('rel'));
    }

    public function testAQuietLocationKeepsItsDatePagesOutOfTheIndex(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch(hits: 3);
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse/ce-week-end');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
    }

    public function testABusyLocationFollowsTheLinksToItsDatePages(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch(hits: 3);
        $this->createBusyToulouse();

        $crawler = $client->request('GET', '/toulouse');

        self::assertNull($crawler->filter('#agenda-dates a[href="/toulouse/ce-week-end"]')->attr('rel'));
        self::assertNull($crawler->filter('#agenda-dates a[href="/toulouse/ce-soir"]')->attr('rel'));
        // The other shortcuts change every day and have no page of their own
        self::assertSame('nofollow', $crawler->filter('#agenda-dates a[href="/toulouse?when=tomorrow"]')->attr('rel'));
    }

    public function testABusyLocationHasItsDatePagesIndexed(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch(hits: 3);
        $this->createBusyToulouse();

        $client->request('GET', '/toulouse/ce-week-end');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('meta[name="robots"]');
    }

    public function testAnEmptyDatePageIsNotIndexed(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse/ce-soir');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideOtherUrlsOfAPeriod(): iterable
    {
        yield 'the shortcut in the query string' => ['/toulouse?when=this_weekend&term=jazz', '/toulouse/ce-week-end?term=jazz'];
        yield 'a later page' => ['/toulouse/2?when=today', '/toulouse/ce-soir/2'];
        yield 'another shortcut' => ['/toulouse/ce-week-end?when=tomorrow&term=jazz', '/toulouse?term=jazz&when=tomorrow'];
        yield 'every day' => ['/toulouse/ce-week-end?when=anytime', '/toulouse'];
        yield 'the dates picked' => ['/toulouse/ce-week-end?when=&dateRange[from]=2026-10-10&dateRange[to]=2026-10-12', '/toulouse?dateRange%5Bfrom%5D=2026-10-10&dateRange%5Bto%5D=2026-10-12'];
    }

    /**
     * Each period has one URL: the location's agenda with a shortcut that has a page is that page, and a date page
     * searched with other dates is the location's agenda.
     */
    #[DataProvider('provideOtherUrlsOfAPeriod')]
    public function testEveryPeriodHasASingleUrl(string $url, string $target): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch(hits: 60);
        CityFactory::toulouse()->create();

        $client->request('GET', $url);

        self::assertResponseRedirects($target, Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testTheFormSendingItsOwnShortcutAgainStaysOnThePage(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch(hits: 3);
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', '/toulouse/ce-week-end?when=this_weekend&term=jazz');

        self::assertResponseIsSuccessful();
        self::assertSame('https://by-night.test/toulouse/ce-week-end', $crawler->filter('link[rel="canonical"]')->attr('href'));
    }

    public function testATypePageKeepsTheShortcutInItsQueryString(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch(hits: 3);
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse/agenda/sortir/concert?when=this_weekend');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#agenda-dates .btn-chip.active', 'Ce week-end');
    }

    private function createBusyToulouse(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->many(DatePageIndexingPolicy::MIN_UPCOMING_EVENTS)->create(['place' => $place, 'category' => null, 'themes' => []]);
        self::counter()->refresh();
        // The counter writes the counts by query: the page reads them from the entity already loaded
        refresh($toulouse);
    }
}
