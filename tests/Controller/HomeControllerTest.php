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
use App\Tests\Stats\CountsUpcomingEvents;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    use CountsUpcomingEvents;

    public function testTheSearchStartsFromTheMembersCityAndGoesByGetToItsAgenda(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne(['city' => CityFactory::toulouse()]));

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-city-name][value="Toulouse"]');
        self::assertSelectorExists('[data-city-slug][value="toulouse"]');
        self::assertSelectorExists('input[name="when"][value="anytime"]:checked');
        // The city is in the path: its inputs have no name, the query holds the filters of the agenda only
        $form = $crawler->selectButton('Explorer')->form(['term' => 'jazz', 'when' => 'this_weekend']);
        self::assertSame('GET', $form->getMethod());
        self::assertSame('http://localhost/toulouse/agenda?term=jazz&when=this_weekend', $form->getUri());
    }

    public function testWithoutACityTheSearchWaitsForThePicker(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        // pages/index.js points the form at the agenda of the picked city and enables the button
        self::assertSelectorExists('form.form-city-picker[method="get"]:not([action])');
        self::assertSelectorExists('.choose-city-action[disabled]');
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
        self::counter()->refresh();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        // The busiest country is featured until the back office features some
        self::assertSelectorTextSame('#countries .card-lg .h2', '1');
    }

    public function testTheMetropolisesFallBackOnTheBiggestCitiesOfTheFirstCountry(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        CityFactory::createOne(['name' => 'Lyon', 'population' => 500_000, 'country' => $toulouse->getCountry()]);
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['place' => $place]);
        self::counter()->refresh();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(['Lyon', 'Toulouse'], $crawler->filter('#metropolises h3')->each(static fn ($title): string => trim($title->text())));
        // The cards lead to the agendas, which the navigation promotes
        self::assertSelectorExists('#metropolises a[href="/toulouse/agenda"]');
    }

    public function testAMemberReachesTheirEventsFromTheUserMenu(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());
        $events = self::getContainer()->get('router')->generate('app_event_list');

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        // In the user menu of phones and of desktops, not in the main navigation any more
        self::assertCount(2, $crawler->filter(\sprintf('.nav-avatar .dropdown-menu a[href="%s"]', $events)));
        self::assertCount(0, $crawler->filter(\sprintf('.navbar-nav .nav-link[href="%s"]', $events)));
    }
}
