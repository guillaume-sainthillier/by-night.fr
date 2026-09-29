<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Repository;

use App\Entity\City;
use App\Entity\Country;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;
use App\Tests\Stats\CountsUpcomingEvents;
use DateTimeImmutable;

/**
 * The metropolises and the country cards of the home and country pages, in the order the back office sets.
 */
final class PortalRankingTest extends AppKernelTestCase
{
    use CountsUpcomingEvents;

    public function testTheMetropolisesAreTheFlaggedCitiesRankedFirstThenByPopulation(): void
    {
        $france = CountryFactory::france()->create();
        CityFactory::createOne(['name' => 'Paris', 'population' => 2_000_000, 'metropolis' => true, 'country' => $france]);
        CityFactory::createOne(['name' => 'Lyon', 'population' => 500_000, 'metropolis' => true, 'country' => $france]);
        CityFactory::createOne(['name' => 'Toulouse', 'population' => 470_000, 'metropolis' => true, 'displayOrder' => 1, 'country' => $france]);
        CityFactory::createOne(['name' => 'Nantes', 'population' => 300_000, 'metropolis' => true, 'displayOrder' => 0, 'country' => $france]);
        CityFactory::createOne(['name' => 'Marseille', 'population' => 870_000, 'country' => $france]);

        $metropolises = self::getContainer()->get(CityRepository::class)->findMetropolises(10);

        self::assertSame(['Nantes', 'Toulouse', 'Paris', 'Lyon'], array_map(static fn (City $city): ?string => $city->getName(), $metropolises));
    }

    public function testTheCountriesWithEventsToComeListTheFeaturedFirstThenByRankThenTheBusiest(): void
    {
        $this->withEvents(CountryFactory::createOne(['id' => 'CH', 'displayName' => 'Suisse']), ['Genève' => 1]);
        $this->withEvents(CountryFactory::createOne(['id' => 'BE', 'displayName' => 'Belgique']), ['Bruxelles' => 2]);
        $this->withEvents(CountryFactory::createOne(['id' => 'MC', 'displayName' => 'Monaco', 'featured' => true, 'displayOrder' => 2]), ['Monaco' => 1]);
        $this->withEvents(CountryFactory::createOne(['id' => 'FR', 'displayName' => 'France', 'featured' => true, 'displayOrder' => 1]), ['Paris' => 1]);
        $this->withEvents(CountryFactory::createOne(['id' => 'RE', 'displayName' => 'La Réunion', 'displayOrder' => 1]), ['Saint-Denis' => 1]);
        CountryFactory::createOne(['id' => 'LU', 'displayName' => 'Luxembourg', 'featured' => true]);
        self::counter()->refresh();

        $countries = self::getContainer()->get(EventRepository::class)->findUpcomingCountries(5);

        // Luxembourg has nothing to come, Belgium is busier than Switzerland
        self::assertSame(['FR', 'MC', 'RE', 'BE', 'CH'], array_map(static fn (array $row): ?string => $row[0]->getId(), $countries));
    }

    public function testEachCountryComesWithItsBusiestCitiesAndTheCurrentOneCanBeLeftOut(): void
    {
        $france = CountryFactory::france()->create();
        $this->withEvents($france, ['Lyon' => 3, 'Nantes' => 1, 'Lille' => 2]);
        $this->withEvents(CountryFactory::belgium()->create(), ['Bruxelles' => 1]);
        self::counter()->refresh();
        $repository = self::getContainer()->get(EventRepository::class);

        $cities = static fn (array $countries): array => array_combine(
            array_map(static fn (array $row): ?string => $row[0]->getId(), $countries),
            array_map(static fn (array $row): array => array_map(static fn (City $city): ?string => $city->getName(), $row['cities']), $countries),
        );

        self::assertSame(['FR' => ['Lyon', 'Lille'], 'BE' => ['Bruxelles']], $cities($repository->findUpcomingCountries(2)));
        self::assertSame(['BE' => ['Bruxelles']], $cities($repository->findUpcomingCountries(2, $france)));
    }

    /**
     * @param array<string, int> $cities the events to come of each city
     */
    private function withEvents(Country $country, array $cities): void
    {
        foreach ($cities as $name => $events) {
            $place = PlaceFactory::createOne(['city' => CityFactory::createOne(['name' => $name, 'country' => $country]), 'country' => $country]);
            EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->many($events)->create(['place' => $place]);
        }
    }
}
