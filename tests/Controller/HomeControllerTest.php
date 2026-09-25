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
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class HomeControllerTest extends WebTestCase
{
    public function testTheCityPickerRedirectsToTheCityAgenda(): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $client->request('POST', '/', ['name' => 'Toulouse', 'city' => 'toulouse']);

        self::assertResponseRedirects();
        self::assertStringStartsWith('/toulouse/agenda', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testTheCountriesCountOnlyTheirPublishedUpcomingEvents(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $published = EventFactory::new()->withDates($tomorrow)->create(['place' => $place]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $place, 'draft' => true]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $place, 'duplicateOf' => $published]);
        EventFactory::new()->withDates(new DateTimeImmutable('-10 days'))->create(['place' => $place]);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.card-subtitle', '1 événement à découvrir');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideForeignCities(): iterable
    {
        yield 'leading slash' => ['/evil.example'];
        yield 'leading backslash' => ['\evil.example'];
        yield 'absolute url' => ['https://evil.example'];
    }

    #[DataProvider('provideForeignCities')]
    public function testTheCityPickerNeverRedirectsOffSite(string $city): void
    {
        $client = self::createClient();

        $client->request('POST', '/', ['name' => 'Toulouse', 'city' => $city]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertFalse($client->getResponse()->headers->has('Location'));
    }
}
