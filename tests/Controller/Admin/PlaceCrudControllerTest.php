<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\AdminZone1Factory;
use App\Factory\AdminZone2Factory;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\PlaceLegacySlugFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class PlaceCrudControllerTest extends AppWebTestCase
{
    public function testTheMergePreselectsThePlaceWithTheMostEventsAndMergesIntoTheOneChosen(): void
    {
        $client = $this->createAdminClient();
        $toulouse = CityFactory::toulouse()->create();
        $zenith = PlaceFactory::createOne(['name' => 'Zénith Toulouse', 'slug' => 'zenith-toulouse', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $metropole = PlaceFactory::createOne(['name' => 'Zénith Toulouse Métropole', 'slug' => 'zenith-toulouse-metropole', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $metropoleId = $metropole->getId();
        EventFactory::createOne(['place' => $zenith]);
        EventFactory::createMany(2, ['place' => $metropole]);

        $crawler = $client->request('GET', '/_administration/place/merge', ['ids' => [$zenith->getId(), $metropoleId]]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf('input[name="target"][value="%d"][checked]', $metropoleId));
        self::assertSelectorNotExists(\sprintf('input[name="target"][value="%d"][checked]', $zenith->getId()));

        // The admin may keep another one
        $form = $crawler->selectButton('Fusionner dans le lieu choisi')->form(['target' => (string) $zenith->getId()]);
        $client->submit($form);

        self::assertResponseRedirects(\sprintf('/_administration/place/%d', $zenith->getId()));
        self::assertSame(0, PlaceFactory::count(['id' => $metropoleId]));
        self::assertSame(3, EventFactory::count(['place' => $zenith]));
        self::assertSame($zenith->getId(), PlaceLegacySlugFactory::find(['slug' => 'zenith-toulouse-metropole'])->getPlace()->getId());
    }

    public function testTheMergeNeedsAValidToken(): void
    {
        $client = $this->createAdminClient();
        $places = PlaceFactory::createMany(2);

        $client->request('POST', '/_administration/place/merge?' . http_build_query(['ids' => [$places[0]->getId(), $places[1]->getId()]]), [
            'target' => $places[0]->getId(),
            '_token' => 'forged',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(2, PlaceFactory::count());
    }

    public function testASinglePlaceIsNotMerged(): void
    {
        $client = $this->createAdminClient();
        $place = PlaceFactory::createOne();

        $client->request('GET', '/_administration/place/merge', ['ids' => [$place->getId()]]);

        self::assertResponseRedirects('/_administration/place');
    }

    public function testTheIndexCanListThePlacesOfARegion(): void
    {
        $client = $this->createAdminClient();
        $occitanie = AdminZone1Factory::createOne(['name' => 'Occitanie']);
        $hauteGaronne = AdminZone2Factory::createOne(['name' => 'Haute-Garonne', 'parent' => $occitanie]);
        // A city hangs from its département or directly from its region
        PlaceFactory::createOne(['name' => 'Zénith Toulouse', 'city' => CityFactory::new(['parent' => $hauteGaronne])]);
        PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => CityFactory::new(['parent' => $occitanie])]);
        PlaceFactory::createOne(['name' => 'Zénith de Paris', 'city' => CityFactory::new(['parent' => AdminZone1Factory::new(['name' => 'Île-de-France'])])]);

        $client->request('GET', '/_administration/place', ['filters' => ['region' => (string) $occitanie->getId()]]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Zénith Toulouse');
        self::assertSelectorTextContains('table', 'Le Bikini');
        self::assertSelectorTextNotContains('table', 'Zénith de Paris');
    }

    private function createAdminClient(): KernelBrowser
    {
        // createClient() first: factories boot the kernel and WebTestCase refuses a late client
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        return $client;
    }
}
