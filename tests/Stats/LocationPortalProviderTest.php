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
     * @return array{cities: list<array{0: City, events: int|string}>, neighbours: array<mixed>}
     */
    private function portalOf(Country $country): array
    {
        return self::getContainer()->get(LocationPortalProvider::class)->getPortal(new Location()->setCountry($country));
    }
}
