<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Stats;

use App\Entity\City;
use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Repository\CityRepository;
use App\Repository\CountryRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Stats\AdvertiserFiguresProvider;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;

final class AdvertiserFiguresProviderTest extends AppKernelTestCase
{
    use CountsUpcomingEvents;

    public function testCountsTheEventsToComeTheirPlacesTheMembersAndTheSources(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $lyon = CityFactory::createOne(['name' => 'Lyon', 'country' => $toulouse->getCountry()]);
        $belgium = CountryFactory::createOne(['id' => 'BE', 'name' => 'Belgique', 'displayName' => 'Belgique']);
        $switzerland = CountryFactory::createOne(['id' => 'CH', 'name' => 'Suisse', 'displayName' => 'Suisse']);
        $member = UserFactory::createOne();
        UserFactory::new()->enabled(false)->create();
        $tomorrow = new DateTimeImmutable('tomorrow');
        // Two published by the member, three imported
        EventFactory::new()->withDates($tomorrow)->many(2)->create(['user' => $member, 'place' => $this->placeIn($toulouse)]);
        EventFactory::new()->withDates($tomorrow)->create(['user' => null, 'place' => $this->placeIn($lyon)]);
        EventFactory::new()->withDates($tomorrow)->create(['user' => null, 'place' => $this->placeIn(CityFactory::createOne(['country' => $belgium]))]);
        // Over, or not published: no event to come, nor a city or a country covered
        EventFactory::new()->withDates(new DateTimeImmutable('-10 days'))->create(['user' => null, 'place' => $this->placeIn(CityFactory::createOne(['country' => $switzerland]))]);
        EventFactory::new()->withDates($tomorrow)->create(['user' => $member, 'draft' => true, 'place' => $this->placeIn($toulouse)]);
        self::counter()->refresh();

        $figures = self::getContainer()->get(AdvertiserFiguresProvider::class)->get();

        self::assertNotNull($figures);
        self::assertSame(4, $figures->upcomingEvents);
        self::assertSame(3, $figures->cities);
        self::assertSame(2, $figures->countries);
        self::assertSame(1, $figures->members);
        self::assertSame(2, $figures->memberEvents);
        // The ticketing feeds apart from the agendas, the development parser left out
        self::assertEqualsCanonicalizing(['Fnac Spectacles', 'Billets Reduc', 'SeeTickets (ex Digitick)', 'CDiscount', 'Fever'], $figures->ticketingSources);
        self::assertContains('Open Agenda', $figures->agendaSources);
        self::assertContains('Data Tourisme', $figures->agendaSources);
        self::assertNotContains('test', $figures->agendaSources);
        self::assertSame(\count($figures->agendaSources) + 5, $figures->sources());
    }

    public function testKeepsTheFiguresADay(): void
    {
        $provider = self::getContainer()->get(AdvertiserFiguresProvider::class);
        self::assertSame(0, $provider->get()?->members);

        UserFactory::createOne();

        self::assertSame(0, $provider->get()?->members);
        self::getContainer()->get('redis.app_cache_pool')->delete(AdvertiserFiguresProvider::CACHE_KEY);
        self::assertSame(1, $provider->get()?->members);
    }

    public function testHasNoFiguresWhenTheyCannotBeCounted(): void
    {
        $cache = new class extends ArrayAdapter implements CacheInterface {
            public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
            {
                throw new RuntimeException('Redis is down');
            }
        };
        $provider = new AdvertiserFiguresProvider(
            self::getContainer()->get(CountryRepository::class),
            self::getContainer()->get(CityRepository::class),
            self::getContainer()->get(EventRepository::class),
            self::getContainer()->get(UserRepository::class),
            $cache,
            new NullLogger(),
            [],
        );

        self::assertNull($provider->get());
    }

    private function placeIn(City $city): Place
    {
        return PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]);
    }
}
