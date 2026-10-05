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
use App\Tests\Controller\Location\StubsAgendaSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class AppContextSubscriberTest extends WebTestCase
{
    use StubsAgendaSearch;

    /**
     * @return iterable<string, array{string}>
     */
    public static function providePagesWithoutLocation(): iterable
    {
        yield 'login' => ['/login'];
        yield 'search' => ['/recherche/'];
        yield 'registration' => ['/inscription'];
    }

    #[DataProvider('providePagesWithoutLocation')]
    public function testTheFormerCityCookieIsIgnored(string $url): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();
        // Browsers keep the cookie of the last city visited for a year after it stopped being written
        $client->getCookieJar()->set(new Cookie('app_city', 'toulouse'));

        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#navbar-main a[href^="/toulouse"]');
    }

    public function testACityPageNoLongerSetsACookie(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse');

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasCookie('app_city');
    }

    public function testAnUnknownCityInTheUrlIsStillNotFound(): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();

        // The slug of the URL is readable without loading the city: what needs the city itself still 404s
        $client->request('GET', '/ville-inconnue');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
