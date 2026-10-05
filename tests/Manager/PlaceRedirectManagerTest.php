<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Manager;

use App\App\Location;
use App\Entity\City;
use App\Exception\RedirectException;
use App\Factory\CityFactory;
use App\Factory\PlaceFactory;
use App\Factory\PlaceLegacySlugFactory;
use App\Manager\PlaceRedirectManager;
use App\Repository\PlaceRepository;
use App\Tests\AppKernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PlaceRedirectManagerTest extends AppKernelTestCase
{
    public function testThePlaceOfTheCityTheUrlNamesIsReturned(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        self::assertSame($bikini, $this->createManager()->getPlace('le-bikini', $this->locationOf($toulouse)));
    }

    public function testAPlaceOfAnotherCityRedirectsToItsOwnUrlWithItsFilters(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $ramonville = CityFactory::createOne(['name' => 'Ramonville-Saint-Agne', 'country' => $toulouse->getCountry()]);
        PlaceFactory::createOne(['slug' => 'le-bikini', 'city' => $ramonville, 'country' => $ramonville->getCountry()]);

        $this->assertRedirects(
            \sprintf('/%s/agenda/sortir-a/le-bikini?type=concert', $ramonville->getSlug()),
            Response::HTTP_MOVED_PERMANENTLY,
            fn () => $this->createManager('/?type=concert')->getPlace('le-bikini', $this->locationOf($toulouse)),
        );
    }

    public function testTheSlugOfAMergedPlaceRedirectsToThePlaceThatTookIt(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        PlaceLegacySlugFactory::createOne(['place' => $bikini, 'slug' => 'le-bikini-1']);

        $this->assertRedirects(
            '/toulouse/agenda/sortir-a/le-bikini',
            Response::HTTP_MOVED_PERMANENTLY,
            fn () => $this->createManager()->getPlace('le-bikini-1', $this->locationOf($toulouse)),
        );
    }

    public function testAnUnknownPlaceLeadsToTheLocationPageForNow(): void
    {
        $toulouse = CityFactory::toulouse()->create();

        $this->assertRedirects(
            '/toulouse',
            Response::HTTP_FOUND,
            fn () => $this->createManager()->getPlace('nowhere', $this->locationOf($toulouse)),
        );
    }

    public function testTheLegacyUrlWithoutAPlaceLeadsToThePlaceItsQueryNames(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        PlaceFactory::createOne(['slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        $this->assertRedirects(
            '/toulouse/agenda/sortir-a/le-bikini',
            Response::HTTP_MOVED_PERMANENTLY,
            fn () => $this->createManager('/?slug=le-bikini')->getPlace(null, $this->locationOf($toulouse)),
        );
        $this->assertRedirects(
            '/toulouse',
            Response::HTTP_MOVED_PERMANENTLY,
            fn () => $this->createManager()->getPlace(null, $this->locationOf($toulouse)),
        );
    }

    /**
     * @param callable(): mixed $getPlace
     */
    private function assertRedirects(string $url, int $statusCode, callable $getPlace): void
    {
        try {
            $getPlace();
            self::fail('A redirect was expected');
        } catch (RedirectException $exception) {
            self::assertSame($url, $exception->getUrl());
            self::assertSame($statusCode, $exception->getStatusCode());
        }
    }

    private function createManager(string $uri = '/'): PlaceRedirectManager
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create($uri));

        return new PlaceRedirectManager(
            $requestStack,
            self::getContainer()->get(UrlGeneratorInterface::class),
            self::getContainer()->get(PlaceRepository::class),
        );
    }

    private function locationOf(City $city): Location
    {
        return new Location()->setCity($city);
    }
}
