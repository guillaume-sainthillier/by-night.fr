<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Entity\City;
use App\Entity\Country;
use App\Repository\CityRepository;
use App\Repository\CountryRepository;
use ReflectionClass;
use RuntimeException;

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
        $lazyCity = $reflector->newLazyProxy(function () use ($slug): City {
            return $this->cityRepository->findOneBySlug($slug)
                ?? throw new RuntimeException(\sprintf('City with slug "%s" not found', $slug));
        });

        $location = new Location();
        $location->setCity($lazyCity);

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
        $lazyCountry = $reflector->newLazyProxy(function () use ($slug): Country {
            return $this->countryRepository->findOneBy(['slug' => $slug])
                ?? throw new RuntimeException(\sprintf('Country with slug "%s" not found', $slug));
        });

        $location = new Location();
        $location->setCountry($lazyCountry);

        return $location;
    }
}
