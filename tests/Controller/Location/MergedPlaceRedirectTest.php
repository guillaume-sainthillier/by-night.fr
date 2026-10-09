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
use App\Factory\PlaceLegacySlugFactory;
use App\Tests\AppWebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The agenda page of a place merged into another one leads to the other one's.
 */
final class MergedPlaceRedirectTest extends AppWebTestCase
{
    use StubsAgendaSearch;

    public function testTheSlugOfAMergedPlaceRedirectsToThePlaceThatTookItsEvents(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        PlaceLegacySlugFactory::createOne(['place' => $bikini, 'slug' => 'le-bikini-1', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini-1?type=concert');

        self::assertResponseRedirects('/toulouse/agenda/sortir-a/le-bikini?type=concert', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testALiveSlugOfTheCityWinsOverAMergedOne(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        PlaceFactory::createOne(['name' => 'Le Bikini 1', 'slug' => 'le-bikini-1', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        PlaceLegacySlugFactory::createOne(['place' => $bikini, 'slug' => 'le-bikini-1', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini-1');

        // The place named le-bikini-1 keeps its page
        self::assertResponseIsSuccessful();
    }

    public function testAMergedSlugOfAnotherCityIsNotFollowed(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $albi = CityFactory::createOne(['name' => 'Albi', 'country' => $toulouse->getCountry()]);
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'slug' => 'le-bikini', 'city' => $albi, 'country' => $albi->getCountry()]);
        PlaceLegacySlugFactory::createOne(['place' => $bikini, 'slug' => 'le-bikini-1', 'city' => $albi, 'country' => $albi->getCountry()]);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini-1');

        // No place of that slug: back to the city page
        self::assertResponseRedirects('/toulouse');
    }

    public function testTheSlugOfAPlaceMergedIntoOneOfAnotherCityRedirectsFromItsOwnCity(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $blagnac = CityFactory::createOne(['name' => 'Blagnac', 'country' => $toulouse->getCountry()]);
        $zenith = PlaceFactory::createOne(['name' => 'Zénith Toulouse Métropole', 'slug' => 'zenith-toulouse-metropole', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        PlaceLegacySlugFactory::createOne(['place' => $zenith, 'slug' => 'zenith', 'city' => $blagnac, 'country' => $blagnac->getCountry()]);

        $client->request('GET', \sprintf('/%s/agenda/sortir-a/zenith', $blagnac->getSlug()));

        self::assertResponseRedirects('/toulouse/agenda/sortir-a/zenith-toulouse-metropole', Response::HTTP_MOVED_PERMANENTLY);
    }
}
