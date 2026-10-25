<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Elastica\NodePool\RoundRobinResurrect;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

use function Zenstruck\Foundry\Persistence\delete;

/**
 * The error pages as production renders them (no debug): a client error in the site's layout, with the way back to an
 * agenda, a server error in a shell of its own.
 */
final class ErrorPageTest extends AppWebTestCase
{
    public function testAPageNoRouteMatchesHasTheSiteAroundIt(): void
    {
        $client = self::createClient(['debug' => false]);

        $crawler = $client->request('GET', '/a/b/c/d/e');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertClientErrorPage($crawler, "Cette page n'existe pas (ou plus)");
        self::assertSame('/recherche/', $crawler->filter('form[role="search"]')->attr('action'));
    }

    /**
     * The header reads the city of the URL, which no city has: it would fail again on the error page, which then
     * left Symfony's bare page instead.
     */
    public function testTheCityOfTheUrlThatDoesNotExistIsLeftOut(): void
    {
        $client = self::createClient(['debug' => false]);
        CityFactory::createOne(['name' => 'Lyon', 'metropolis' => true]);

        $crawler = $client->request('GET', '/nulle-part/agenda/sortir/concert');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertClientErrorPage($crawler, "Cette page n'existe pas (ou plus)");
        self::assertSelectorTextContains('main', 'Sortir par métropole');
        self::assertCount(1, $crawler->filter('main a[href="/lyon"]'));
    }

    public function testThePageNotFoundInACityLeadsToItsAgenda(): void
    {
        $client = self::createClient(['debug' => false]);
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', '/toulouse/soiree/introuvable--999999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertClientErrorPage($crawler, "Cette page n'existe pas (ou plus)");
        self::assertSelectorTextContains('main', 'Envie de sortir à Toulouse');
        self::assertCount(1, $crawler->filter('main a.btn[href="/toulouse"]'));
        self::assertCount(1, $crawler->filter('main a[href="/toulouse/agenda/sortir/concert"]'));
        self::assertSame('toulouse', $crawler->filter('form[role="search"] input[name="city"]')->attr('value'));
    }

    public function testWhatIsGoneUnderNowhereLeadsToTheMetropolises(): void
    {
        $client = self::createClient(['debug' => false]);
        CityFactory::createOne(['name' => 'Lyon', 'metropolis' => true]);

        $crawler = $client->request('GET', '/unknown/soiree/introuvable--999999999');

        self::assertResponseStatusCodeSame(Response::HTTP_GONE);
        $this->assertClientErrorPage($crawler, "Cette page n'existe plus");
        self::assertCount(1, $crawler->filter('main a[href="/lyon"]'));
    }

    public function testADeletedEventLeadsToTheAgendaOfItsCity(): void
    {
        $client = self::createClient(['debug' => false]);
        $toulouse = CityFactory::toulouse()->create();
        $deleted = EventFactory::createOne(['place' => PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()])]);
        EventFactory::createOne();
        $url = \sprintf('/toulouse/soiree/%s--%d', $deleted->getSlug(), $deleted->getId());
        delete($deleted);

        $crawler = $client->request('GET', $url);

        self::assertResponseStatusCodeSame(Response::HTTP_GONE);
        $this->assertClientErrorPage($crawler, "Cette page n'existe plus");
        self::assertCount(1, $crawler->filter('main a.btn[href="/toulouse"]'));
    }

    public function testAMemberRefusedAPageKeepsTheirMenu(): void
    {
        $client = self::createClient(['debug' => false]);
        $member = UserFactory::createOne();
        $client->loginUser($member);

        $crawler = $client->request('GET', self::organizerPath(UserFactory::createOne()));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertClientErrorPage($crawler, 'Cette page ne vous est pas ouverte');
        // The member's menu, not the login of a visitor
        self::assertCount(0, $crawler->filter('a[href^="/login"]'));
        self::assertGreaterThan(0, $crawler->filter('header .nav-avatar')->count());
    }

    public function testTheSearchEngineDownShowsTheMaintenanceShell(): void
    {
        $client = $this->createClientWithElasticsearch(info: ['error' => 'Connection refused']);

        $crawler = $client->request('GET', '/recherche/?q=concert');

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        $this->assertServerErrorPage($crawler, 'By Night fait une courte pause');
        self::assertCount(0, $crawler->filter('form[role="search"]'));
    }

    public function testAServerErrorShowsTheShell(): void
    {
        $client = $this->createClientWithElasticsearch(
            '{"error":{"type":"illegal_state_exception","reason":"boom"},"status":500}',
            ['http_code' => Response::HTTP_INTERNAL_SERVER_ERROR, 'response_headers' => ['content-type' => 'application/json', 'x-elastic-product' => 'Elasticsearch']],
        );

        $crawler = $client->request('GET', '/recherche/?q=concert');

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->assertServerErrorPage($crawler, 'Un imprévu de notre côté');
        self::assertSame('/recherche/?q=concert', $crawler->filter('main a.btn-primary')->attr('href'));
    }

    private function assertClientErrorPage(Crawler $crawler, string $title): void
    {
        self::assertSelectorTextSame('h1', $title);
        self::assertSame('noindex', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertCount(0, $crawler->filter('link[rel="canonical"]'));
        // The site's own header (its search field) and footer (its countries)
        self::assertCount(1, $crawler->filter('header #autocomplete'));
        self::assertCount(1, $crawler->filter('#footer #footer-countries'));
        self::assertCount(1, $crawler->filter('main a[href="mailto:support@by-night.fr"]'));
    }

    private function assertServerErrorPage(Crawler $crawler, string $title): void
    {
        self::assertSelectorTextSame('h1', $title);
        self::assertSame('noindex', $crawler->filter('meta[name="robots"]')->attr('content'));
        // Neither the header nor the footer of the site, which read the database
        self::assertCount(0, $crawler->filter('#autocomplete, #footer-countries, script'));
        self::assertCount(1, $crawler->filter('main a[href="/"]'));
        self::assertCount(1, $crawler->filter('main a[href="mailto:support@by-night.fr"]'));
    }

    /**
     * Without debug, an Elastica client whose HTTP requests all get the response given (see ElasticsearchOutageTest).
     *
     * @param array<string, mixed> $info the MockResponse options
     */
    private function createClientWithElasticsearch(string $body = '', array $info = []): KernelBrowser
    {
        $client = self::createClient(['debug' => false]);

        $http = new MockHttpClient(static fn (): MockResponse => new MockResponse($body, $info));
        $elastica = new Client([
            'hosts' => ['http://elasticsearch.test:9200'],
            'transport_config' => ['http_client' => new Psr18Client($http), 'node_pool' => RoundRobinResurrect::create()],
        ]);
        self::getContainer()->set('fos_elastica.client.default', $elastica);

        return $client;
    }
}
