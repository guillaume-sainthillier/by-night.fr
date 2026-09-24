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
use App\Factory\PlaceFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AgendaControllerTest extends WebTestCase
{
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
}
