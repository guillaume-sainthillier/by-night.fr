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
use App\Factory\PlaceLegacySlugFactory;
use App\Factory\PlaceMetadataFactory;
use App\Factory\PlaceNameSlugFactory;
use App\Factory\ZipCityFactory;
use App\Tests\AppKernelTestCase;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * City-less places merged across towns are split along the towns their events name, and a
 * city-less place gets the city its postal code confirms.
 */
final class PlacesFixCitylessCommandTest extends AppKernelTestCase
{
    private Country $france;

    private City $toulouse;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->france = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        $this->toulouse = CityFactory::createOne(['name' => 'Toulouse', 'admin2Code' => '31', 'population' => 471_941, 'country' => $this->france]);
        ZipCityFactory::createOne(['parent' => $this->toulouse, 'postalCode' => '31000', 'country' => $this->france]);
    }

    public function testAPlaceMergedAcrossTownsIsSplitAlongThem(): void
    {
        $merged = $this->citylessPlace('Salle des fêtes', 'Tuffalun', '49700');
        PlaceMetadataFactory::createOne(['place' => $merged, 'externalId' => 'oa-batz']);
        $inTuffalun = $this->eventAt($merged, '49700', 'Tuffalun', 2);
        $inBatz = $this->eventAt($merged, '44740', 'Batz Sur Mer', 2);
        $unknownTown = EventFactory::createOne(['place' => $merged, 'placePostalCode' => null, 'placeCity' => null]);
        // Another merged place with events in Batz: they join the place the split made there
        $other = $this->citylessPlace('Salle des fêtes', 'Montaigu-Vendée', '85600');
        $this->eventAt($other, '85600', 'Montaigu-Vendée', 1);
        $alsoInBatz = $this->eventAt($other, '44740', 'Batz-sur-Mer', 1);

        $display = $this->doRunCommand(['--apply' => true])->getDisplay();

        self::assertStringContainsString('Splitting 2 city-less place(s)', $display);
        foreach ([...$inTuffalun, $unknownTown] as $event) {
            self::assertSame($merged->getId(), $this->placeOf($event));
        }

        $batz = PlaceFactory::find(['id' => $this->placeOf($inBatz[0])]);
        self::assertNotSame($merged->getId(), $batz->getId());
        self::assertSame(['Salle des fêtes', '44740', 'Batz Sur Mer', null], [$batz->getName(), $batz->getCityPostalCode(), $batz->getCityName(), $batz->getCity()]);
        self::assertSame($batz->getId(), $this->placeOf($inBatz[1]));
        self::assertSame($batz->getId(), $this->placeOf($alsoInBatz[0]), 'One place per town');
        self::assertSame(0, PlaceMetadataFactory::count(['place' => $merged]), 'Its identities cannot be told apart: the next import attaches them again');
    }

    public function testAPlaceOfASingleTownIsNotSplit(): void
    {
        $place = $this->citylessPlace('Salle des fêtes', 'Tuffalun', '49700');
        PlaceMetadataFactory::createOne(['place' => $place]);
        $this->eventAt($place, '49700', 'Tuffalun', 2);

        $this->doRunCommand(['--apply' => true]);

        self::assertSame(1, PlaceFactory::count());
        self::assertSame(1, PlaceMetadataFactory::count(['place' => $place]));
    }

    public function testACitylessPlaceGetsTheCityItsPostalCodeConfirms(): void
    {
        $place = $this->citylessPlace('Le Bikini', 'TOULOUSE', '31000');
        PlaceNameSlugFactory::createOne(['place' => $place, 'slug' => 'bikini', 'country' => $this->france]);
        $paris = CityFactory::createOne(['name' => 'Paris', 'admin2Code' => '75', 'country' => $this->france]);
        ZipCityFactory::createOne(['parent' => $paris, 'postalCode' => '75018', 'country' => $this->france]);
        $unnamedTown = $this->citylessPlace('Le Trianon', 'PARIS 18EME', '75018');
        CityFactory::createOne(['name' => 'Saint-Denis', 'admin2Code' => '93', 'country' => $this->france]);
        $reunion = $this->citylessPlace('Théâtre Champ Fleuri', 'Saint-Denis', '97400');

        $this->doRunCommand(['--apply' => true]);

        self::assertSame($this->toulouse->getId(), PlaceFactory::find(['id' => $place->getId()])->getCity()?->getId());
        self::assertSame($this->toulouse->getId(), PlaceNameSlugFactory::find(['slug' => 'bikini'])->getCity()?->getId());
        self::assertSame($paris->getId(), PlaceFactory::find(['id' => $unnamedTown->getId()])->getCity()?->getId(), 'A postal code of a single city locates it');
        self::assertNull(PlaceFactory::find(['id' => $reunion->getId()])->getCity(), 'Saint-Denis (93) is not of 97400');
    }

    public function testACitylessPlaceIsMergedIntoTheSameVenueOfItsCity(): void
    {
        $venue = PlaceFactory::createOne(['name' => 'Le Bikini', 'slug' => 'le-bikini', 'street' => 'Rue Théodore Monod', 'city' => $this->toulouse, 'country' => $this->france]);
        $place = $this->citylessPlace('Le Bikini', 'Toulouse', '31000', ['street' => 'rue Théodore-Monod', 'latitude' => null, 'longitude' => null]);
        PlaceMetadataFactory::createOne(['place' => $place, 'externalId' => 'oa-bikini']);
        $events = $this->eventAt($place, '31000', 'Toulouse', 2);

        $display = $this->doRunCommand(['--apply' => true])->getDisplay();

        self::assertSame(0, PlaceFactory::count(['id' => $place->getId()]));
        foreach ($events as $event) {
            self::assertSame($venue->getId(), $this->placeOf($event));
        }

        self::assertSame(1, PlaceMetadataFactory::count(['place' => $venue, 'externalId' => 'oa-bikini']), 'The next imports find the venue');
        self::assertSame(1, PlaceLegacySlugFactory::count(['place' => $venue, 'slug' => 'le-bikini', 'city' => null, 'country' => $this->france]), 'Its page, under the country, redirects');
        self::assertMatchesRegularExpression('/\b0\s+1\s+0\s+0\b/', $display, 'Counted as merged');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, bool}>
     */
    public static function namesakes(): iterable
    {
        yield 'on the same street' => [['street' => '4 Square Violette'], ['street' => '4, square Violette'], true];
        yield 'within 300 m' => [['street' => 'Quai François-Mauriac', 'latitude' => 43.6, 'longitude' => 1.44], ['street' => 'Site François-Mitterrand', 'latitude' => 43.6015, 'longitude' => 1.4410], true];
        yield 'a street missing, nothing apart' => [['street' => null], ['street' => 'Rue Théodore Monod'], true];
        yield 'a street missing, close' => [['street' => null, 'latitude' => 43.6, 'longitude' => 1.44], ['street' => 'Rue Théodore Monod', 'latitude' => 43.605, 'longitude' => 1.44], true];
        yield 'two streets' => [['street' => '196 Traverse de la Penne'], ['street' => '24 Route d\'Allauch'], false];
        yield 'a street missing, 5 km apart' => [['street' => null, 'latitude' => 43.6, 'longitude' => 1.44], ['street' => 'Rue Théodore Monod', 'latitude' => 43.645, 'longitude' => 1.44], false];
    }

    /**
     * @param array<string, mixed> $citylessAddress
     * @param array<string, mixed> $venueAddress
     */
    #[DataProvider('namesakes')]
    public function testAPlaceOfThatNameInTheCityIsTheSameVenueWhenTheAddressesAgree(array $citylessAddress, array $venueAddress, bool $sameVenue): void
    {
        // Without coordinates unless the case gives some: the factory draws them anywhere
        $noCoordinates = ['latitude' => null, 'longitude' => null];
        $venue = PlaceFactory::createOne(['name' => 'Salle Paul Éluard', 'slug' => 'salle-paul-eluard', 'city' => $this->toulouse, 'country' => $this->france] + $venueAddress + $noCoordinates);
        $place = $this->citylessPlace('Salle Paul Éluard', 'Toulouse', '31000', $citylessAddress + $noCoordinates);
        [$event] = $this->eventAt($place, '31000', 'Toulouse', 1);

        $this->doRunCommand(['--apply' => true]);

        if ($sameVenue) {
            self::assertSame($venue->getId(), $this->placeOf($event));
            self::assertSame(0, PlaceFactory::count(['id' => $place->getId()]));
        } else {
            self::assertSame($place->getId(), $this->placeOf($event), 'Another venue of that name, located beside it');
            self::assertSame($this->toulouse->getId(), PlaceFactory::find(['id' => $place->getId()])->getCity()?->getId());
        }
    }

    public function testPreviewWritesNothing(): void
    {
        $merged = $this->citylessPlace('Salle des fêtes', 'Tuffalun', '49700');
        $this->eventAt($merged, '49700', 'Tuffalun', 1);
        $inBatz = $this->eventAt($merged, '44740', 'Batz Sur Mer', 1);
        $located = $this->citylessPlace('Le Bikini', 'Toulouse', '31000');
        PlaceFactory::createOne(['name' => 'Salle Paul Éluard', 'slug' => 'salle-paul-eluard', 'city' => $this->toulouse, 'country' => $this->france]);
        $twin = $this->citylessPlace('Salle Paul Éluard', 'Toulouse', '31000');

        $display = $this->doRunCommand([])->getDisplay();

        self::assertStringContainsString('Preview only', $display);
        self::assertSame($merged->getId(), $this->placeOf($inBatz[0]));
        self::assertSame(4, PlaceFactory::count());
        self::assertNull(PlaceFactory::find(['id' => $located->getId()])->getCity());
        self::assertNull(PlaceFactory::find(['id' => $twin->getId()])->getCity(), 'Not merged either');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function citylessPlace(string $name, string $town, string $postalCode, array $attributes = []): Place
    {
        return PlaceFactory::createOne(['name' => $name, 'city' => null, 'cityName' => $town, 'cityPostalCode' => $postalCode, 'country' => $this->france] + $attributes);
    }

    /**
     * @return list<\App\Entity\Event>
     */
    private function eventAt(Place $place, string $postalCode, string $town, int $count): array
    {
        return EventFactory::createMany($count, ['place' => $place, 'placePostalCode' => $postalCode, 'placeCity' => $town]);
    }

    private function placeOf(\App\Entity\Event $event): ?int
    {
        return EventFactory::find(['id' => $event->getId()])->getPlace()?->getId();
    }

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:places:fix-cityless'));
        $tester->execute($input);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester;
    }
}
