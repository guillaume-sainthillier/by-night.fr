<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\PlaceMetadataFactory;
use App\Factory\PlaceNameSlugFactory;
use App\Tests\AppKernelTestCase;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The places are indexed under the slug their name has now, and the places of a city sharing one are merged when
 * they are one venue.
 */
final class PlacesMergeDuplicatesCommandTest extends AppKernelTestCase
{
    private Country $france;

    private City $paris;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->france = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        $this->paris = CityFactory::createOne(['name' => 'Paris', 'admin2Code' => '75', 'country' => $this->france]);
    }

    public function testTheSpellingsOfAVenueAreMergedIntoOne(): void
    {
        $venue = $this->place("Ancienne église Saint-Honoré-d'Eylau", ['street' => '9 place Victor-Hugo']);
        PlaceNameSlugFactory::createOne(['place' => $venue, 'slug' => 'ancienne eglise sainthonoredeylau', 'city' => $this->paris, 'country' => $this->france]);
        EventFactory::createMany(2, ['place' => $venue]);
        $spelling = $this->place("Ancienne église Saint-Honoré d'Eylau", ['street' => '9, place Victor Hugo']);
        PlaceMetadataFactory::createOne(['place' => $spelling, 'externalId' => 'datatourisme-1']);
        $event = EventFactory::createOne(['place' => $spelling]);

        $this->doRunCommand(['--apply' => true]);

        self::assertSame(0, PlaceFactory::count(['id' => $spelling->getId()]));
        self::assertSame($venue->getId(), EventFactory::find(['id' => $event->getId()])->getPlace()?->getId());
        self::assertSame(1, PlaceMetadataFactory::count(['place' => $venue, 'externalId' => 'datatourisme-1']));
        self::assertSame(1, PlaceNameSlugFactory::count(['place' => $venue, 'slug' => 'ancienne eglise saint honore d eylau', 'city' => $this->paris]), 'The next imports find it');
    }

    public function testThePlacesNamedAfterTheirTownAreMergedWhenOneVenue(): void
    {
        $town = $this->place('Paris');
        EventFactory::createMany(2, ['place' => $town]);
        $again = $this->place('PARIS');
        $atAnAddress = $this->place('Paris', ['street' => '12 rue de Rivoli']);
        $sameAddress = $this->place('Paris', ['street' => '12, rue de Rivoli']);

        $this->doRunCommand(['--apply' => true]);

        self::assertSame(0, PlaceFactory::count(['id' => $again->getId()]));
        self::assertSame(1, PlaceFactory::count(['id' => $atAnAddress->getId()]), 'Somewhere in the town is not at an address');
        self::assertSame(0, PlaceFactory::count(['id' => $sameAddress->getId()]), 'At one address');
        self::assertSame(1, PlaceNameSlugFactory::count(['place' => $town, 'slug' => 'paris']));
    }

    public function testAVenueWithoutAStreetIsMergedIntoItsPlaceOnAStreet(): void
    {
        $museum = $this->place('Musée Réattu', ['street' => '10 rue du Grand Prieuré']);
        $withoutStreet = $this->place('Musée Réattu');
        $elsewhere = $this->place('Musée Réattu', ['street' => '3 avenue Foch']);

        $this->doRunCommand(['--apply' => true]);

        self::assertSame(0, PlaceFactory::count(['id' => $withoutStreet->getId()]));
        self::assertSame(1, PlaceFactory::count(['id' => $museum->getId()]));
        self::assertSame(1, PlaceFactory::count(['id' => $elsewhere->getId()]), 'Another street: another venue, which the one without a street does not join to the first');
    }

    public function testThePlacesOfOtherCitiesAndNamesStayApart(): void
    {
        $lyon = CityFactory::createOne(['name' => 'Lyon', 'admin2Code' => '69', 'country' => $this->france]);
        $bikini = $this->place('Le Bikini');
        $this->place('Le Bikini', ['city' => $lyon]);
        $this->place('La Cigale');
        $cityless = $this->place('Le Bikini', ['city' => null, 'cityName' => 'Paris']);

        $this->doRunCommand(['--apply' => true]);

        self::assertSame(4, PlaceFactory::count());
        self::assertSame(1, PlaceNameSlugFactory::count(['place' => $bikini, 'slug' => 'bikini', 'city' => $this->paris]));
        self::assertSame(1, PlaceNameSlugFactory::count(['place' => $cityless, 'slug' => 'bikini', 'city' => null, 'country' => $this->france]), 'Indexed under its country');
    }

    public function testASlugIsNotAddedTwice(): void
    {
        $bikini = $this->place('Le Bikini');
        PlaceNameSlugFactory::createOne(['place' => $bikini, 'slug' => 'bikini', 'city' => $this->paris, 'country' => $this->france]);

        $display = $this->doRunCommand(['--apply' => true])->getDisplay();

        self::assertSame(1, PlaceNameSlugFactory::count(['place' => $bikini]));
        self::assertMatchesRegularExpression('/\b0\s+0\s+0\s+0\b/', $display);
    }

    public function testPreviewWritesNothing(): void
    {
        $venue = $this->place('Saint-Eustache');
        $spelling = $this->place('Saint Eustache');

        $display = $this->doRunCommand([])->getDisplay();

        self::assertStringContainsString('Preview only', $display);
        self::assertMatchesRegularExpression('/\b2\s+1\s+1\s+0\b/', $display, 'Counted: 2 slugs, 1 venue, 1 place merged');
        self::assertSame(2, PlaceFactory::count());
        self::assertSame(0, PlaceNameSlugFactory::count(['place' => $venue]));
        self::assertSame(0, PlaceNameSlugFactory::count(['place' => $spelling]));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function place(string $name, array $attributes = []): Place
    {
        // Without coordinates: the factory draws them anywhere
        return PlaceFactory::createOne($attributes + ['name' => $name, 'city' => $this->paris, 'country' => $this->france, 'street' => null, 'latitude' => null, 'longitude' => null]);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:places:merge-duplicates'));
        $tester->execute($input);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester;
    }
}
