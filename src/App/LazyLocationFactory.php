<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Entity\AdminZone;
use App\Entity\City;
use App\Entity\Country;
use App\Repository\CityRepository;
use App\Repository\CountryRepository;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Factory for creating lazy-loaded Location objects using PHP 8.4 LazyObject.
 *
 * This allows creating Location objects with lazy City/Country entities that
 * are only loaded from the database when their properties are actually accessed,
 * similar to Doctrine proxy behavior.
 */
final readonly class LazyLocationFactory
{
    public function __construct(
        private CityRepository $cityRepository,
        private CountryRepository $countryRepository,
    ) {
    }

    /**
     * Create a Location with a lazy-loaded City.
     * The City entity will only be loaded from the database when accessed.
     */
    public function createWithLazyCity(string $slug): Location
    {
        $reflector = new ReflectionClass(City::class);

        /** @var City $lazyCity */
        $lazyCity = $reflector->newLazyProxy(fn (): City => $this->cityRepository->findOneBySlug($slug)
            ?? throw new NotFoundHttpException(\sprintf('City with slug "%s" not found', $slug)));
        // The URL already carries the slug: handing it to the proxy lets whoever only needs it (the
        // cookie refresh of CitySubscriber, a link back to the city) read it without any query.
        new ReflectionProperty(AdminZone::class, 'slug')->setRawValueWithoutLazyInitialization($lazyCity, $slug);

        $location = new Location();
        $location->setCity($lazyCity);

        return $location;
    }

    /**
     * Create a Location with its City loaded right away, or null when no city has this slug. For a
     * slug the visitor sends back (the app_city cookie), which may name a city renamed or merged since:
     * a lazy City would only fail when first read, from a template, as a server error.
     */
    public function createWithCity(string $slug): ?Location
    {
        $city = $this->cityRepository->findOneBySlug($slug);
        if (null === $city) {
            return null;
        }

        $location = new Location();
        $location->setCity($city);

        return $location;
    }

    /**
     * Create a Location with a lazy-loaded Country.
     * The Country entity will only be loaded from the database when accessed.
     */
    public function createWithLazyCountry(string $slug): Location
    {
        $reflector = new ReflectionClass(Country::class);

        /** @var Country $lazyCountry */
        $lazyCountry = $reflector->newLazyProxy(fn (): Country => $this->countryRepository->findOneBy(['slug' => $slug])
            ?? throw new NotFoundHttpException(\sprintf('Country with slug "%s" not found', $slug)));
        new ReflectionProperty(Country::class, 'slug')->setRawValueWithoutLazyInitialization($lazyCountry, $slug);

        $location = new Location();
        $location->setCountry($lazyCountry);

        return $location;
    }
}
