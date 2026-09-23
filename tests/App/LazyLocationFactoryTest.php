<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\App;

use App\App\LazyLocationFactory;
use App\Entity\City;
use App\Factory\CityFactory;
use App\Tests\AppKernelTestCase;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LazyLocationFactoryTest extends AppKernelTestCase
{
    public function testTheSlugOfTheUrlIsReadWithoutLoadingTheCity(): void
    {
        CityFactory::toulouse()->create();

        $city = $this->getFactory()->createWithLazyCity('toulouse')->getCity();

        self::assertInstanceOf(City::class, $city);
        self::assertSame('toulouse', $city->getSlug());
        self::assertTrue(new ReflectionClass(City::class)->isUninitializedLazyObject($city), 'Reading the slug must not query the city');
    }

    public function testAnythingElseLoadsTheCity(): void
    {
        CityFactory::toulouse()->create();

        $city = $this->getFactory()->createWithLazyCity('toulouse')->getCity();
        self::assertInstanceOf(City::class, $city);

        self::assertSame('Toulouse', $city->getName());
        self::assertFalse(new ReflectionClass(City::class)->isUninitializedLazyObject($city));
    }

    public function testAnUnknownCityIsReportedWhenItIsRead(): void
    {
        $city = $this->getFactory()->createWithLazyCity('ville-inconnue')->getCity();
        self::assertInstanceOf(City::class, $city);

        $this->expectException(NotFoundHttpException::class);
        $city->getName();
    }

    public function testTheCookieCityIsResolvedRightAway(): void
    {
        CityFactory::toulouse()->create();

        $location = $this->getFactory()->createWithCity('toulouse');

        self::assertNotNull($location);
        self::assertSame('Toulouse', $location->getCity()?->getName());
        self::assertNull($this->getFactory()->createWithCity('ville-inconnue'), 'An unknown slug leaves the visitor without a location');
    }

    private function getFactory(): LazyLocationFactory
    {
        return self::getContainer()->get(LazyLocationFactory::class);
    }
}
