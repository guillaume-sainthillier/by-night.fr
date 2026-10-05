<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\App;

use App\Factory\CityFactory;
use App\Factory\UserFactory;
use App\Tests\Controller\Location\StubsAgendaSearch;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AppContextTest extends WebTestCase
{
    use StubsAgendaSearch;

    public function testThePagesWithoutLocationTakeTheMembersCity(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne(['city' => CityFactory::toulouse()]));

        $client->request('GET', '/recherche/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#navbar-main a[href="/toulouse"]');
    }

    public function testTheCityOfTheUrlWinsOverTheMembersCity(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        $toulouse = CityFactory::toulouse()->create();
        CityFactory::createOne(['name' => 'Lyon', 'slug' => 'lyon', 'country' => $toulouse->getCountry()]);
        $client->loginUser(UserFactory::createOne(['city' => $toulouse]));

        $client->request('GET', '/lyon');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#navbar-main a[href="/lyon"]');
        self::assertSelectorNotExists('#navbar-main a[href="/toulouse"]');
    }

    public function testAMemberWithoutCityHasNoLocation(): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/recherche/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#navbar-main a[href="/toulouse"]');
    }
}
