<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EventSubscriber;

use App\Factory\CityFactory;
use App\Tests\AppWebTestCase;
use App\Tests\Controller\Location\StubsAgendaSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * The front controller in the URL ("/index.php/paris/soiree/…") answered the page, with the prefix in its canonical:
 * it leads to the URL without it in one redirect.
 */
final class FrontControllerUrlSubscriberTest extends AppWebTestCase
{
    use StubsAgendaSearch;

    /** What Caddy's php_server gives PHP: the script it runs, which Symfony reads the base URL from */
    private const array FRONT_CONTROLLER = ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => '/app/public/index.php'];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideUrlsWithTheFrontController(): iterable
    {
        yield 'event page' => ['/index.php/paris/soiree/217e-messe-des-charcutiers--4268999', '/paris/soiree/217e-messe-des-charcutiers--4268999'];
        yield 'with its query string' => ['/index.php/toulouse/ce-soir?term=jazz&page=2', '/toulouse/ce-soir?term=jazz&page=2'];
        yield 'the front controller alone' => ['/index.php', '/'];
        yield 'with a trailing slash' => ['/index.php/', '/'];
        yield 'the home page with a query string' => ['/index.php?utm_source=x', '/?utm_source=x'];
    }

    #[DataProvider('provideUrlsWithTheFrontController')]
    public function testTheFrontControllerLeavesTheUrl(string $formerUrl, string $url): void
    {
        $client = self::createClient();

        $client->request('GET', $formerUrl, server: self::FRONT_CONTROLLER);

        self::assertResponseRedirects($url, Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testItLeavesTheUrlWhereverTheServerTakesTheBaseUrlFrom(): void
    {
        $client = self::createClient();

        $client->request('GET', '/index.php/paris/soiree/217e-messe-des-charcutiers--4268999');

        self::assertResponseRedirects('/paris/soiree/217e-messe-des-charcutiers--4268999', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testAFormPostedThereIsPostedAgain(): void
    {
        $client = self::createClient();

        $client->request('POST', '/index.php/login', server: self::FRONT_CONTROLLER);

        self::assertResponseRedirects('/login', Response::HTTP_PERMANENTLY_REDIRECT);
    }

    /**
     * Only a path starting with the front controller: "/index.phpx" or an event named "index.php" are other pages.
     */
    public function testAPathThatOnlyLooksLikeItIsLeftAlone(): void
    {
        $client = self::createClient();

        $client->request('GET', '/index.phpx/paris');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testThePagesTheFrontControllerRunsHaveTheirCanonicalUrl(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch(hits: 3);
        CityFactory::toulouse()->create();

        $crawler = $client->request('GET', '/toulouse', server: self::FRONT_CONTROLLER);

        self::assertResponseIsSuccessful();
        self::assertSame('https://by-night.test/toulouse', $crawler->filter('link[rel="canonical"]')->attr('href'));
    }
}
