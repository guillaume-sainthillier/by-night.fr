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
use App\Factory\FollowFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;

/**
 * The "Suivre" button of a venue's agenda.
 */
final class PlaceFollowTest extends AppWebTestCase
{
    use StubsAgendaSearch;

    public function testAVisitorFollowsTheVenueOnceLoggedIn(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        $toulouse = CityFactory::toulouse()->create();
        PlaceFactory::createOne(['name' => 'Le Bikini', 'slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.agenda-header-card a[href="/login?_target_path=/toulouse/agenda/sortir-a/le-bikini%23suivre"]');
    }

    public function testAMemberSeesTheyFollowTheVenue(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        $member = UserFactory::createOne();
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        FollowFactory::createOne(['user' => $member, 'publisher' => null, 'place' => $bikini]);
        $client->loginUser($member);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini');

        self::assertSelectorExists(\sprintf('.agenda-header-card .btn-follow.btn-primary[data-follow="place"][data-follow-id="%d"]', $bikini->getId()));
        self::assertSelectorTextContains('.agenda-header-card [data-followers]', '1 abonné');
    }
}
