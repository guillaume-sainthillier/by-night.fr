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
use App\Factory\PlaceMetadataFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

final class PlaceCrudControllerTest extends AppWebTestCase
{
    public function testTheIndexListsPlaces(): void
    {
        $client = $this->createAdminClient();
        PlaceFactory::createOne(['name' => 'Zénith Toulouse']);
        PlaceFactory::createOne(['name' => 'Le Bikini']);

        $client->request('GET', '/_administration/place');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Zénith Toulouse');
        self::assertSelectorTextContains('table', 'Le Bikini');
    }

    public function testTheDetailShowsAPlaceWithItsMetadatas(): void
    {
        $client = $this->createAdminClient();
        $place = PlaceFactory::createOne(['name' => 'Zénith Toulouse']);
        PlaceMetadataFactory::createMany(2, ['place' => $place]);

        $client->request('GET', \sprintf('/_administration/place/%d', $place->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Zénith Toulouse');
        self::assertSelectorTextContains('body', '2');
    }

    public function testCreatingAPlaceWorks(): void
    {
        $client = $this->createAdminClient();
        $city = CityFactory::toulouse()->create();
        $country = $city->getCountry();

        $crawler = $client->request('GET', '/_administration/place/new');
        $token = $crawler->filter('input[name="Place[_token]"]')->attr('value');

        $client->request('POST', '/_administration/place/new', [
            'Place' => [
                'name' => 'Nouveau lieu',
                'street' => '123 Rue de la Paix',
                'slug' => 'nouveau-lieu',
                'city' => ['autocomplete' => (string) $city->getId()],
                'country' => ['autocomplete' => (string) $country->getId()],
                '_token' => $token,
            ],
            'saveAndReturn' => 'Sauvegarder les modifications',
        ]);

        self::assertResponseRedirects();
        $place = PlaceFactory::find(['name' => 'Nouveau lieu']);
        self::assertSame('123 Rue de la Paix', $place->getStreet());
        self::assertSame($city->getId(), $place->getCity()->getId());
        self::assertSame($country->getId(), $place->getCountry()->getId());
    }

    public function testEditingAPlaceWorks(): void
    {
        $client = $this->createAdminClient();
        $place = PlaceFactory::createOne(['name' => 'Ancien nom', 'slug' => 'ancien-nom']);

        $client->request('GET', \sprintf('/_administration/place/%d/edit', $place->getId()));
        $client->submitForm('Sauvegarder les modifications', [
            'Place[name]' => 'Nouveau nom',
            'Place[street]' => '456 Avenue de la République',
            'Place[facebookId]' => '123456789',
        ]);

        self::assertResponseRedirects();
        $place = PlaceFactory::find(['id' => $place->getId()]);
        self::assertSame('Nouveau nom', $place->getName());
        self::assertSame('456 Avenue de la République', $place->getStreet());
        self::assertSame('123456789', $place->getFacebookId());
    }

    public function testDeletingAPlaceWorks(): void
    {
        $client = $this->createAdminClient();
        $place = PlaceFactory::createOne();
        $placeId = $place->getId();

        $crawler = $client->request('GET', \sprintf('/_administration/place/%d', $placeId));
        $token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $client->request('POST', \sprintf('/_administration/place/%d/delete', $placeId), ['token' => $token]);

        self::assertResponseRedirects();
        self::assertSame(0, PlaceFactory::count(['id' => $placeId]));
    }

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

    /**
     * No URL indexed before the merge is lost: the page of the place merged away and the pages of its events, now in
     * the city of the place kept, answer with a permanent redirect.
     */
    public function testTheUrlsOfAPlaceMergedIntoOneOfAnotherCityRedirectPermanently(): void
    {
        $client = $this->createAdminClient();
        $toulouse = CityFactory::toulouse()->create();
        $blagnac = CityFactory::createOne(['name' => 'Blagnac', 'country' => $toulouse->getCountry()]);
        $zenith = PlaceFactory::createOne(['name' => 'Zénith Toulouse Métropole', 'slug' => 'zenith-toulouse-metropole', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $duplicate = PlaceFactory::createOne(['name' => 'Zénith', 'slug' => 'zenith', 'city' => $blagnac, 'country' => $blagnac->getCountry()]);
        $event = EventFactory::createOne(['place' => $duplicate]);
        $oldEventUrl = \sprintf('/%s/soiree/%s--%d', $blagnac->getSlug(), $event->getSlug(), $event->getId());

        $client->request('POST', '/_administration/place/merge?' . http_build_query(['ids' => [$zenith->getId(), $duplicate->getId()]]), [
            'target' => $zenith->getId(),
            '_token' => $this->getCsrfToken($client),
        ]);
        self::assertResponseRedirects();

        $client->request('GET', \sprintf('/%s/agenda/sortir-a/zenith', $blagnac->getSlug()));
        self::assertResponseRedirects('/toulouse/agenda/sortir-a/zenith-toulouse-metropole', Response::HTTP_MOVED_PERMANENTLY);

        $client->request('GET', $oldEventUrl);
        self::assertResponseRedirects(\sprintf('/toulouse/soiree/%s--%d', $event->getSlug(), $event->getId()), Response::HTTP_MOVED_PERMANENTLY);
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

    /**
     * The token of the merge form, as the page hands it out.
     */
    private function getCsrfToken(KernelBrowser $client): string
    {
        $places = PlaceFactory::createMany(2);
        $crawler = $client->request('GET', '/_administration/place/merge', ['ids' => [$places[0]->getId(), $places[1]->getId()]]);

        return (string) $crawler->filter('input[name="_token"]')->attr('value');
    }

    private function createAdminClient(): KernelBrowser
    {
        // createClient() first: factories boot the kernel and WebTestCase refuses a late client
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        return $client;
    }
}
