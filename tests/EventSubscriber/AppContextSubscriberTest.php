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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class AppContextSubscriberTest extends WebTestCase
{
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
    public function testACookieNamingAVanishedCityIsIgnored(string $url): void
    {
        $client = self::createClient();
        $client->getCookieJar()->set(new Cookie('app_city', 'ville-renommee-depuis'));

        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    public function testACookieNamingAKnownCitySetsTheLocation(): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();
        $client->getCookieJar()->set(new Cookie('app_city', 'toulouse'));

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href^="/toulouse"]');
    }
}
