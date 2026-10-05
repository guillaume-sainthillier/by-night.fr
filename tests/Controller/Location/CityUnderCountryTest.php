<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Location;

use App\Entity\City;
use App\Factory\CityFactory;
use App\Factory\CityLegacySlugFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The cities of a country that prefixes its cities' URLs live under it ("/suisse/geneve"); their former URLs
 * ("/geneve-1/…") reach the new ones in a single redirect.
 */
final class CityUnderCountryTest extends WebTestCase
{
    use StubsAgendaSearch;

    public function testTheCityPageIsUnderItsCountry(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        $this->geneva();

        $client->request('GET', '/suisse/geneve');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.hero-location h1', 'Que faire à Genève');
    }

    public function testTheCountryKeepsItsOwnPages(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        $this->geneva();

        // "agenda" is no city of Switzerland: the Swiss concerts
        $client->request('GET', '/suisse/agenda/sortir/concert');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Concerts en Suisse');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFormerUrls(): iterable
    {
        yield 'city page' => ['/geneve-1', '/suisse/geneve'];
        yield 'with its trailing slash' => ['/geneve-1/', '/suisse/geneve'];
        yield 'former agenda' => ['/geneve-1/agenda', '/suisse/geneve'];
        yield 'former agenda page, with its filters' => ['/geneve-1/agenda/2?when=this_weekend', '/suisse/geneve/2?when=this_weekend'];
        yield 'type page' => ['/geneve-1/agenda/sortir/concert', '/suisse/geneve/agenda/sortir/concert'];
    }

    #[DataProvider('provideFormerUrls')]
    public function testAFormerUrlRedirectsOnceToTheNewOne(string $formerUrl, string $url): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        CityLegacySlugFactory::createOne(['city' => $this->geneva(), 'slug' => 'geneve-1']);

        $client->request('GET', $formerUrl);

        self::assertResponseRedirects($url, Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testAFormerEventUrlRedirectsToTheEventUnderTheCountry(): void
    {
        $client = self::createClient();
        $geneva = $this->geneva();
        CityLegacySlugFactory::createOne(['city' => $geneva, 'slug' => 'geneve-1']);
        $place = PlaceFactory::createOne(['city' => $geneva, 'country' => $geneva->getCountry()]);
        $event = EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['name' => 'Fête de l\'Escalade', 'place' => $place]);

        $client->request('GET', \sprintf('/geneve-1/soiree/%s--%d', $event->getSlug(), $event->getId()));

        self::assertResponseRedirects(\sprintf('/suisse/geneve/soiree/%s--%d', $event->getSlug(), $event->getId()), Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testAnUnknownCityIsStillNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', '/geneve-2');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function geneva(): City
    {
        return CityFactory::createOne(['name' => 'Genève', 'country' => CountryFactory::switzerland()]);
    }
}
