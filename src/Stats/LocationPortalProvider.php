<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Stats;

use App\App\Location;
use App\Entity\City;
use App\Entity\Country;
use App\Repository\CityRepository;
use App\Repository\EventRepository;

/**
 * Which countries and cities the home page and the location pages put forward, in which order, from their stored
 * counts of events to come (UpcomingEventCounter).
 */
final readonly class LocationPortalProvider
{
    /** The busiest cities on each country card, of the home page and of the country pages alike: they share the query */
    private const int CITIES_PER_COUNTRY = 5;

    /** Below, a country has no ranking of its cities */
    private const int MIN_CITIES = 3;

    private const int NEIGHBOURS = 5;

    private const int METROPOLISES = 5;

    public function __construct(
        private EventRepository $eventRepository,
        private CityRepository $cityRepository,
    ) {
    }

    /**
     * The countries with events to come, the busiest first, with their busiest cities: the cards of the home page.
     *
     * @return list<array{0: Country, events: int|string, cities: list<City>}>
     */
    public function getCountries(): array
    {
        return $this->eventRepository->findUpcomingCountries(self::CITIES_PER_COUNTRY);
    }

    /**
     * What introduces a location on its first page: the busiest cities of a country, and its neighbours, the cities
     * around a city or the other countries.
     *
     * @return array{
     *     cities: list<array{0: City, events: int|string}>,
     *     neighbours: list<array{0: City, events: int|string}>|list<array{0: Country, events: int|string, cities: list<City>}>,
     * }
     */
    public function getPortal(Location $location): array
    {
        $city = $location->getCity();
        if (null !== $city) {
            return ['cities' => [], 'neighbours' => $this->eventRepository->findUpcomingCitiesAround($city, self::NEIGHBOURS)];
        }

        $country = $location->getCountry();
        if (null === $country) {
            return ['cities' => [], 'neighbours' => []];
        }

        $cities = $this->eventRepository->findUpcomingCitiesOfCountry($country, self::CITIES_PER_COUNTRY);

        return [
            // A country with too few busy cities to rank (Monaco) has its venues in the filters of its agenda
            'cities' => \count($cities) >= self::MIN_CITIES ? $cities : [],
            // The other countries, as the home page lists them
            'neighbours' => $this->eventRepository->findUpcomingCountries(self::CITIES_PER_COUNTRY, $country),
        ];
    }

    /**
     * The cities the home page puts forward: the ones the back office flags, until it flags some the biggest of a
     * country.
     *
     * @return City[]
     */
    public function getMetropolises(?Country $country): array
    {
        $metropolises = $this->cityRepository->findMetropolises(self::METROPOLISES);
        if ([] === $metropolises && null !== $country) {
            return $this->cityRepository->findBiggestOfCountry((string) $country->getSlug(), self::METROPOLISES);
        }

        return $metropolises;
    }
}
