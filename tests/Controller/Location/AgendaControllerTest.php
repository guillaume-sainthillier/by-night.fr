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
use App\Factory\TagFactory;
use App\Tests\Stats\CountsUpcomingEvents;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

final class AgendaControllerTest extends WebTestCase
{
    use CountsUpcomingEvents;

    public function testALegacyPlaceUrlRedirectsToThePlaceInItsOwnCity(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $ramonville = CityFactory::createOne(['name' => 'Ramonville-Saint-Agne', 'country' => $toulouse->getCountry()]);
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $ramonville, 'country' => $ramonville->getCountry()]);

        // The sitemap used to submit places as "?slug=…" under the city of the place
        $client->request('GET', '/toulouse/agenda/sortir-a?slug=le-bikini');

        self::assertResponseRedirects(
            \sprintf('/%s/agenda/sortir-a/le-bikini', $ramonville->getSlug()),
            Response::HTTP_MOVED_PERMANENTLY
        );
    }

    public function testALegacyPlaceUrlPrefersThePlaceInTheCityItNames(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $albi = CityFactory::createOne(['name' => 'Albi', 'country' => $toulouse->getCountry()]);
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $albi, 'country' => $albi->getCountry()]);
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        $client->request('GET', '/toulouse/agenda/sortir-a?slug=le-bikini');

        self::assertResponseRedirects('/toulouse/agenda/sortir-a/le-bikini', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testAPlaceUrlShowsThePlaceOfTheCityItNames(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $albi = CityFactory::createOne(['name' => 'Albi', 'country' => $toulouse->getCountry()]);
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $albi, 'country' => $albi->getCountry()]);
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        // An invalid filter skips the Elasticsearch query: the place lookup still runs first
        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini?range=not-a-number');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAPlaceUrlNamingAnotherCityRedirectsToThePlace(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $albi = CityFactory::createOne(['name' => 'Albi', 'country' => $toulouse->getCountry()]);
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $albi, 'country' => $albi->getCountry()]);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini');

        self::assertResponseRedirects(\sprintf('/%s/agenda/sortir-a/le-bikini', $albi->getSlug()));
    }

    public function testALegacyPlaceUrlWithAnUnknownPlaceRedirectsToTheCityAgenda(): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse/agenda/sortir-a?slug=nowhere');

        self::assertResponseRedirects('/toulouse/agenda', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testTheAgendaByPlaceWithoutAPlaceRedirectsToTheCityAgenda(): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse/agenda/sortir-a');

        self::assertResponseRedirects('/toulouse/agenda', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testAListingWithoutResultsIsNotIndexable(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        CityFactory::toulouse()->create();

        // An invalid filter skips the Elasticsearch query and renders the listing with no result
        $client->request('GET', '/toulouse/agenda?range=not-a-number');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
    }

    /**
     * The period of a quick search (QuickSearchType) or of a chip is named in the URLs, which stay true the next week.
     */
    public function testTheLinksKeepTheShortcutByItsName(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', '/toulouse/agenda?term=jazz&when=this_weekend&range=not-a-number');

        $query = $this->queryOf($crawler->filter('#agenda-filters a[href^="/toulouse/agenda/sortir/"]')->first()->attr('href'));
        self::assertSame('this_weekend', $query['when'] ?? null);
        self::assertArrayNotHasKey('dateRange', $query);
        self::assertSame('Ce week-end', trim($crawler->filter('#agenda-filters .btn-chip.active')->text()));
        self::assertSelectorExists('#search-form input[type="hidden"][name="when"][value="this_weekend"]');
    }

    public function testTheDatesPickedWinOverTheShortcut(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', '/toulouse/agenda?when=this_weekend&dateRange[from]=2026-10-10&dateRange[to]=2026-10-12&range=not-a-number');

        $query = $this->queryOf($crawler->filter('#agenda-filters a[href^="/toulouse/agenda/sortir/"]')->first()->attr('href'));
        self::assertSame(['from' => '2026-10-10', 'to' => '2026-10-12'], $query['dateRange'] ?? null);
        self::assertArrayNotHasKey('when', $query);
        self::assertCount(0, $crawler->filter('#agenda-filters .btn-chip.active'), 'No shortcut is these dates');
    }

    public function testAnUnknownShortcutIsTousLesJours(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', '/toulouse/agenda?when=next_year&range=not-a-number');

        $query = $this->queryOf($crawler->filter('#agenda-filters a[href^="/toulouse/agenda/sortir/"]')->first()->attr('href'));
        self::assertArrayNotHasKey('when', $query);
        self::assertSame('Tous les jours', trim($crawler->filter('#agenda-filters .btn-chip.active')->text()));
    }

    public function testAPlaceAgendaKeepsThePlaceNameAsWritten(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        PlaceFactory::createOne(['name' => 'Zénith Toulouse Métropole', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        // An invalid filter skips the Elasticsearch query and renders the listing with no result
        $client->request('GET', '/toulouse/agenda/sortir-a/zenith-toulouse-metropole?range=not-a-number');

        // |capitalize used to lower-case everything after the first letter: "Zénith toulouse métropole"
        self::assertSelectorTextContains('h1', 'Zénith Toulouse Métropole');
    }

    public function testAPlaceAgendaContractsThePrepositionWithThePlaceArticle(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini?range=not-a-number');

        self::assertSelectorTextContains('h1', 'Sortir au Bikini');
    }

    public function testACityAgendaContractsThePrepositionWithTheCityArticle(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        $city = CityFactory::createOne(['name' => 'Le Mans']);

        $client->request('GET', \sprintf('/%s/agenda?range=not-a-number', $city->getSlug()));

        self::assertSelectorTextContains('h1', 'Événements au Mans');
    }

    public function testATypeAgendaNamesTheTypeLikeItsHeadingInTheBreadcrumb(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse/agenda/sortir/etudiant?range=not-a-number');

        self::assertSelectorTextContains('h1', 'Soirées étudiantes');
        // The breadcrumb used to show the raw route parameter: "Etudiant"
        self::assertAnySelectorTextSame('.breadcrumb-item', 'Soirées étudiantes');
    }

    /**
     * The search form used to show the synonyms of the type as its keywords, and keywords typed
     * in their place searched without the type.
     */
    public function testTheKeywordsOfATypePageAreTheirOwnAndItsLinksKeepThem(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse/agenda/sortir/concert?range=not-a-number');
        self::assertInputValueSame('term', '');

        $client->request('GET', '/toulouse/agenda/sortir/concert?term=jazz&range=not-a-number');
        self::assertInputValueSame('term', 'jazz');
        self::assertSelectorExists('#agenda-filters a[href="/toulouse/agenda/sortir/spectacle?term=jazz&range=not-a-number"]');
        self::assertSelectorNotExists('#agenda-filters a[data-filtered-href]', 'Without a venue, the href keeps every filter');
    }

    /**
     * The type links are the SEO pages crawlers follow ("Concerts à Toulouse"); a visitor keeps
     * the venue through data-filtered-href (assets/js/listeners/filtered-link.js).
     */
    public function testATypeLinkOfAVenuePageIsTheTypePageAndKeepsTheVenueForAVisitor(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini?term=jazz&range=not-a-number');

        self::assertSelectorExists('#agenda-filters a[href="/toulouse/agenda/sortir/concert?term=jazz&range=not-a-number"][data-filtered-href="/toulouse/agenda/sortir-a/le-bikini?term=jazz&range=not-a-number&type=concert"]');
        self::assertSelectorExists('#agenda-filters a[href="/toulouse/agenda?term=jazz&range=not-a-number"][data-filtered-href="/toulouse/agenda/sortir-a/le-bikini?term=jazz&range=not-a-number"]', '"Toutes les sorties"');
    }

    /**
     * Their counts are the stored ones of the whole city, not the facets of the search: they go once keywords or
     * dates narrow it, while the links keep them.
     */
    public function testTheThemesOfTheMomentLeadToTheirAgendaWithTheirCountsWhileNothingNarrowsTheSearch(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $concert = TagFactory::createOne(['name' => 'Concert']);
        $theatre = TagFactory::createOne(['name' => 'Théâtre']);
        $tomorrow = new DateTimeImmutable('tomorrow');
        EventFactory::new()->withDates($tomorrow)->create(['place' => $place, 'category' => $concert]);
        EventFactory::new()->withDates($tomorrow)->many(2)->create(['place' => $place, 'category' => $theatre]);
        self::counter()->refresh();
        $themes = '#agenda-filters a[href*="/agenda/tag/"]';

        $crawler = $client->request('GET', '/toulouse/agenda?range=not-a-number');

        self::assertSame(
            [['Théâtre', '2'], ['Concert', '1']],
            $crawler->filter($themes)->each(static fn ($link): array => [trim($link->filter('.flex-fill')->text()), trim($link->filter('.badge')->text())]),
        );
        self::assertSame(\sprintf('/toulouse/agenda/tag/theatre--%d?range=not-a-number', $theatre->getId()), $crawler->filter($themes)->first()->attr('href'));

        $crawler = $client->request('GET', '/toulouse/agenda?term=jazz&range=not-a-number');

        self::assertCount(0, $crawler->filter($themes . ' .badge'));
        self::assertSame('jazz', $this->queryOf($crawler->filter($themes)->first()->attr('href'))['term'] ?? null);
    }

    /**
     * The categories under a type lead to the category narrowed down to that type, as a venue takes it: "?type=".
     * Removing either filter keeps the other.
     */
    public function testACategoryPageNarrowedToATypeKeepsBothFilters(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        CityFactory::toulouse()->create();
        $jazz = TagFactory::createOne(['name' => 'Jazz']);

        $crawler = $client->request('GET', \sprintf('/toulouse/agenda/tag/jazz--%d?type=concert&range=not-a-number', $jazz->getId()));

        self::assertSelectorExists('#search-form input[type="hidden"][name="type"][value="concert"]');
        $chips = $crawler->filter('.btn-chip.active[title="Retirer ce filtre"]');
        self::assertSame(
            [['Concerts', \sprintf('/toulouse/agenda/tag/jazz--%d?range=not-a-number', $jazz->getId())], ['Jazz', '/toulouse/agenda/sortir/concert?range=not-a-number']],
            $chips->each(static fn ($chip): array => [trim($chip->text()), $chip->attr('href')]),
        );
    }

    /**
     * A venue page takes the type and the category as "?type=student&tag=…"; removing one filter keeps the others,
     * and the type links keep the venue and the category for a visitor.
     */
    public function testAVenuePageNarrowedToATypeAndACategoryKeepsThemAll(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $jazz = TagFactory::createOne(['name' => 'Jazz']);
        $tag = (string) $jazz->getId();

        $crawler = $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini?when=this_weekend&type=student&tag=' . $tag . '&range=not-a-number');

        self::assertSelectorExists('#search-form input[type="hidden"][name="type"][value="student"]');
        self::assertSelectorExists('#search-form input[type="hidden"][name="tag"][value="' . $tag . '"]');
        self::assertSame([
            'Soirées étudiantes' => ['/toulouse/agenda/sortir-a/le-bikini', ['tag' => $tag]],
            'Le Bikini' => [\sprintf('/toulouse/agenda/tag/jazz--%s', $tag), ['type' => 'student']],
            'Jazz' => ['/toulouse/agenda/sortir-a/le-bikini', ['type' => 'student']],
        ], $this->chipsOf($crawler));

        $href = (string) $crawler->filter('#agenda-filters .filter-group-link[data-filtered-href]')
            ->reduce(static fn ($link): bool => str_starts_with(trim($link->text()), 'Sorties en famille'))
            ->attr('data-filtered-href');
        self::assertStringStartsWith('/toulouse/agenda/sortir-a/le-bikini?', $href);
        self::assertSame(['range' => 'not-a-number', 'when' => 'this_weekend', 'type' => 'family', 'tag' => $tag], $this->queryOf($href));
    }

    /**
     * On a category page narrowed to a type, the type links keep the category for a visitor.
     */
    public function testTheTypeLinksOfACategoryPageKeepTheCategory(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        CityFactory::toulouse()->create();
        $gastronomy = TagFactory::createOne(['name' => 'Gastronomie']);

        $crawler = $client->request('GET', \sprintf('/toulouse/agenda/tag/gastronomie--%d?type=student&when=this_weekend&range=not-a-number', $gastronomy->getId()));

        $links = $crawler->filter('#agenda-filters .filter-group-link[data-filtered-href]');
        self::assertSame(
            \sprintf('/toulouse/agenda/tag/gastronomie--%d?range=not-a-number&when=this_weekend&type=family', $gastronomy->getId()),
            $links->reduce(static fn ($link): bool => str_starts_with(trim($link->text()), 'Sorties en famille'))->attr('data-filtered-href'),
        );
        self::assertSame(
            \sprintf('/toulouse/agenda/tag/gastronomie--%d?range=not-a-number&when=this_weekend', $gastronomy->getId()),
            $links->reduce(static fn ($link): bool => str_starts_with(trim($link->text()), 'Toutes les sorties'))->attr('data-filtered-href'),
        );
    }

    public function testAVenuePageNarrowedToATypeKeepsBothFilters(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini?type=concert&range=not-a-number');

        self::assertSelectorTextContains('h1', 'Concerts au Bikini');
        // A GET form drops the query string of its action
        self::assertSelectorExists('#search-form input[type="hidden"][name="type"][value="concert"]');
        // Each chip removes its own filter only
        self::assertSelectorExists('a[title="Retirer ce filtre"][href="/toulouse/agenda/sortir-a/le-bikini?range=not-a-number"]');
        self::assertSelectorExists('a[title="Retirer ce filtre"][href="/toulouse/agenda/sortir/concert?range=not-a-number"]');
    }

    public function testAVenuePageIgnoresAnUnknownType(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini?type=brocante&range=not-a-number');

        self::assertSelectorTextContains('h1', 'Sortir au Bikini');
        self::assertSelectorNotExists('#search-form input[name="type"]');
    }

    public function testAVenueUrlNamingAnotherCityKeepsItsFilters(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $albi = CityFactory::createOne(['name' => 'Albi', 'country' => $toulouse->getCountry()]);
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $albi, 'country' => $albi->getCountry()]);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini?type=concert&term=jazz');

        self::assertResponseRedirects(\sprintf('/%s/agenda/sortir-a/le-bikini?type=concert&term=jazz', $albi->getSlug()));
    }

    public function testAnUnknownTypeIsNotAnAgendaPage(): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse/agenda/sortir/brocante');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * The listing page reads the event types through the Redis-backed cache, which CI does not run.
     */
    private function requireRedis(): void
    {
        $host = $_SERVER['REDIS_HOST'] ?? $_ENV['REDIS_HOST'] ?? 'localhost';
        $socket = @fsockopen((string) $host, 6379, $errno, $errstr, 1);
        if (false === $socket) {
            self::markTestSkipped(\sprintf('Redis is not reachable on %s:6379', $host));
        }

        fclose($socket);
    }

    /**
     * The chips that remove a filter, but the period ("Ce week-end"): their label, and the path and the query of the
     * page without it, but the period and the radius.
     *
     * @return array<string, array{string, array<string, mixed>}>
     */
    private function chipsOf(Crawler $crawler): array
    {
        $chips = [];
        $crawler->filter('.btn-chip.active[title="Retirer ce filtre"]')->each(function (Crawler $chip) use (&$chips): void {
            $label = trim($chip->text());
            if ('Ce week-end' !== $label) {
                $href = (string) $chip->attr('href');
                $chips[$label] = [(string) parse_url($href, \PHP_URL_PATH), array_diff_key($this->queryOf($href), ['range' => true, 'when' => true])];
            }
        });

        return $chips;
    }

    /**
     * @return array<string, mixed>
     */
    private function queryOf(?string $href): array
    {
        parse_str((string) parse_url((string) $href, \PHP_URL_QUERY), $query);

        return $query;
    }
}
