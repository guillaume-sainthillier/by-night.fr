<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Stats;

use App\App\Location;
use App\Entity\City;
use App\Entity\Country;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Stats\LocationPortalProvider;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

final class LocationPortalProviderTest extends AppKernelTestCase
{
    use CountsUpcomingEvents;

    public function testACountryRanksItsBusiestCitiesOnceItHasEnough(): void
    {
        $france = CountryFactory::france()->create();
        $this->eventsIn($france, ['Lyon' => 3, 'Nantes' => 1]);

        // Two busy cities are too few to rank
        self::assertSame([], $this->portalOf($france)['cities']);

        $this->eventsIn($france, ['Lille' => 2]);

        self::assertSame(['Lyon', 'Lille', 'Nantes'], array_map(static fn (array $row): ?string => $row[0]->getName(), $this->portalOf($france)['cities']));
    }

    public function testACityRanksNoCities(): void
    {
        $toulouse = CityFactory::toulouse()->create();

        $portal = self::getContainer()->get(LocationPortalProvider::class)->getPortal(new Location()->setCity($toulouse));

        self::assertSame([], $portal['cities']);
    }

    public function testACityListsTheNearestTownsTheNeighboursLeaveOut(): void
    {
        $france = CountryFactory::france()->create();
        $aix = CityFactory::createOne(['name' => 'Aix-en-Provence', 'latitude' => 43.5297, 'longitude' => 5.4474, 'country' => $france]);
        // Busier, within 100 km: the neighbours, Marseille and Salon within 30 km too
        $this->eventsAt($france, [
            'Marseille' => [43.2965, 5.3698, 5],
            'Salon-de-Provence' => [43.6400, 5.0970, 4],
            'Toulon' => [43.1242, 5.9280, 4],
            'Avignon' => [43.9493, 4.8055, 4],
            'Arles' => [43.6766, 4.6278, 4],
            // One event each: Apt lies beyond 30 km
            'Pertuis' => [43.6947, 5.5017, 1],
            'Bouc-Bel-Air' => [43.4522, 5.4144, 1],
            'Gardanne' => [43.4547, 5.4692, 1],
            'Apt' => [43.8760, 5.3960, 1],
        ]);

        $portal = self::getContainer()->get(LocationPortalProvider::class)->getPortal(new Location()->setCity($aix));
        $names = static fn (array $rows): array => array_map(static fn (array $row): ?string => $row[0]->getName(), $rows);

        // Four of them tie on their events: their order is the database's
        self::assertEqualsCanonicalizing(['Marseille', 'Salon-de-Provence', 'Toulon', 'Avignon', 'Arles'], $names($portal['neighbours']));
        self::assertSame(['Gardanne', 'Bouc-Bel-Air', 'Pertuis'], $names($portal['nearest']));
    }

    public function testTheMetropolisesFallBackOnTheBiggestCitiesOfTheCountry(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        CityFactory::createOne(['name' => 'Lyon', 'population' => 500_000, 'country' => $toulouse->getCountry()]);
        $provider = self::getContainer()->get(LocationPortalProvider::class);

        self::assertSame(['Lyon', 'Toulouse'], array_map(static fn (City $city): ?string => $city->getName(), $provider->getMetropolises($toulouse->getCountry())));
        self::assertSame([], $provider->getMetropolises(null));
    }

    /**
     * @param array<string, int> $events by city name
     */
    private function eventsIn(Country $country, array $events): void
    {
        foreach ($events as $name => $count) {
            $place = PlaceFactory::createOne(['city' => CityFactory::createOne(['name' => $name, 'country' => $country]), 'country' => $country]);
            EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->many($count)->create(['place' => $place]);
        }

        self::counter()->refresh();
    }

    /**
     * @param array<string, array{float, float, int}> $cities latitude, longitude and number of events, by city name
     */
    private function eventsAt(Country $country, array $cities): void
    {
        foreach ($cities as $name => [$latitude, $longitude, $count]) {
            $city = CityFactory::createOne(['name' => $name, 'latitude' => $latitude, 'longitude' => $longitude, 'population' => 10_000, 'country' => $country]);
            $place = PlaceFactory::createOne(['city' => $city, 'country' => $country]);
            EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->many($count)->create(['place' => $place]);
        }

        self::counter()->refresh();
    }

    /**
     * @return array{cities: list<array{0: City, events: int|string}>, neighbours: array<mixed>, nearest: array<mixed>}
     */
    private function portalOf(Country $country): array
    {
        return self::getContainer()->get(LocationPortalProvider::class)->getPortal(new Location()->setCountry($country));
    }
}
