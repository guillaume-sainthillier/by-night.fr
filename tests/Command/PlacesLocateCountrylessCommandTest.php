<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Entity\Country;
use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\PlaceNameSlugFactory;
use App\Tests\AppKernelTestCase;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A place without a country gets the city a name confirms around its coordinates, else the
 * country of a city right next to it; the places abroad are left for deletion.
 */
final class PlacesLocateCountrylessCommandTest extends AppKernelTestCase
{
    /** Guérande, 1 km is about 0.009° of latitude */
    private const float LATITUDE = 47.33;

    private const float LONGITUDE = -2.43;

    private Country $france;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->france = CountryFactory::france()->create();
    }

    public function testTheTownOfThePlaceNamesACityAroundIt(): void
    {
        // A hamlet nearer than the town named
        $this->cityAt('Kercabellec', 0.001);
        $guerande = $this->cityAt('Guérande', 0.05);
        $place = $this->countrylessPlace('Salle Athanor', 'Guérande');
        PlaceNameSlugFactory::createOne(['place' => $place, 'slug' => 'salle-athanor', 'city' => null, 'country' => null]);
        $events = EventFactory::createMany(2, ['place' => $place, 'placeCountry' => null]);

        $this->doRunCommand(['--apply' => true]);

        $place = PlaceFactory::find(['id' => $place->getId()]);
        self::assertSame($guerande->getId(), $place->getCity()?->getId());
        self::assertSame('FR', $place->getCountry()?->getId());
        self::assertSame([[$guerande->getId(), 'FR']], array_map(
            static fn ($nameSlug): array => [$nameSlug->getCity()?->getId(), $nameSlug->getCountry()?->getId()],
            $place->getNameSlugs()->toArray(),
        ));
        foreach ($events as $event) {
            self::assertSame('FR', EventFactory::find(['id' => $event->getId()])->getPlaceCountry()?->getId());
        }
    }

    public function testTheTownMayStartTheNameOfTheCity(): void
    {
        $this->cityAt('Beslon', 0.001);
        $this->cityAt('La Baule-les-Pins', 0.01, 1_000);
        $laBaule = $this->cityAt('La Baule-Escoublac', 0.03, 16_000);
        $place = $this->countrylessPlace('Palais des congrès Atlantia', 'La Baule');

        $this->doRunCommand(['--apply' => true]);

        self::assertSame($laBaule->getId(), PlaceFactory::find(['id' => $place->getId()])->getCity()?->getId(), 'The most populated');
    }

    public function testThePlaceNameMayHoldTheCity(): void
    {
        $this->cityAt('Saint-Lyphard', 0.002);
        $castres = $this->cityAt('Castres', 0.01);
        $place = $this->countrylessPlace('Place Jean Jaurès, Castres (81)', null);

        $this->doRunCommand(['--apply' => true]);

        self::assertSame($castres->getId(), PlaceFactory::find(['id' => $place->getId()])->getCity()?->getId());
    }

    public function testACityRightNextToItOnlyGivesItsCountry(): void
    {
        $this->cityAt('Grenelle', 0.01);
        $place = $this->countrylessPlace('Maison de la Radio', null);
        $event = EventFactory::createOne(['place' => $place, 'placeCountry' => null]);

        $this->doRunCommand(['--apply' => true]);

        $place = PlaceFactory::find(['id' => $place->getId()]);
        self::assertNull($place->getCity());
        self::assertSame('FR', $place->getCountry()?->getId());
        self::assertSame('FR', EventFactory::find(['id' => $event->getId()])->getPlaceCountry()?->getId());
    }

    public function testAPlaceMergedAcrossTownsOnlyGetsItsCountry(): void
    {
        $this->cityAt('Guérande', 0.01);
        $place = $this->countrylessPlace('Salle des fêtes', 'Guérande');
        EventFactory::createOne(['place' => $place, 'placePostalCode' => '44350']);
        EventFactory::createOne(['place' => $place, 'placePostalCode' => '49700']);

        $this->doRunCommand(['--apply' => true]);

        $place = PlaceFactory::find(['id' => $place->getId()]);
        self::assertNull($place->getCity(), 'app:places:fix-cityless splits it');
        self::assertSame('FR', $place->getCountry()?->getId());
    }

    public function testThePlacesAbroadAreLeft(): void
    {
        // 15 km away, without a name in common
        $this->cityAt('Pornichet', 0.135);
        $abroad = $this->countrylessPlace('Palau de la Música', 'Barcelona');
        $noCoordinates = $this->countrylessPlace('Estadio Metropolitano', 'Madrid', null);

        $display = $this->doRunCommand(['--apply' => true])->getDisplay();

        foreach ([$abroad, $noCoordinates] as $place) {
            self::assertNull(PlaceFactory::find(['id' => $place->getId()])->getCountry());
        }

        self::assertMatchesRegularExpression('/Left: no city close enough \(abroad\)\s+1/', $display);
        self::assertMatchesRegularExpression('/Left: no coordinates\s+1/', $display);
    }

    public function testTheEventsOfAVenueImportedAgainInItsCityMoveThere(): void
    {
        $guerande = $this->cityAt('Guérande', 0.01);
        $twin = PlaceFactory::createOne(['name' => 'Salle Athanor', 'city' => $guerande, 'country' => $this->france]);
        $place = $this->countrylessPlace('Salle Athanor', 'Guérande');
        $events = EventFactory::createMany(2, ['place' => $place, 'placeCountry' => null]);

        $display = $this->doRunCommand(['--apply' => true])->getDisplay();

        foreach ($events as $event) {
            $event = EventFactory::find(['id' => $event->getId()]);
            self::assertSame($twin->getId(), $event->getPlace()?->getId());
            self::assertSame('FR', $event->getPlaceCountry()?->getId());
        }

        self::assertNull(PlaceFactory::find(['id' => $place->getId()])->getCountry(), 'Left empty for app:places:remove-eventless');
        self::assertStringContainsString('1 already had their venue there: their 2 event(s) move to it', $display);
    }

    public function testTwoPlacesOfTheVenueMeetInOnePlace(): void
    {
        $guerande = $this->cityAt('Guérande', 0.01);
        $first = $this->countrylessPlace('Salle Athanor', 'Guérande');
        $second = $this->countrylessPlace('Salle Athanor', 'Guérande');
        $event = EventFactory::createOne(['place' => $second, 'placeCountry' => null]);

        $this->doRunCommand(['--apply' => true]);

        self::assertSame($guerande->getId(), PlaceFactory::find(['id' => $first->getId()])->getCity()?->getId());
        self::assertSame($first->getId(), EventFactory::find(['id' => $event->getId()])->getPlace()?->getId());
        self::assertSame(1, PlaceFactory::count(['city' => $guerande]));
    }

    public function testThePreviewWritesNothing(): void
    {
        $this->cityAt('Guérande', 0.01);
        $place = $this->countrylessPlace('Salle Athanor', 'Guérande');

        $display = $this->doRunCommand([])->getDisplay();

        self::assertNull(PlaceFactory::find(['id' => $place->getId()])->getCountry());
        self::assertMatchesRegularExpression('/Town is a city\s+1/', $display);
        self::assertStringContainsString('Salle Athanor (Guérande) → Guérande, FR', $display);
    }

    /**
     * A city of France, $latitudeOffset degrees north of the places.
     */
    private function cityAt(string $name, float $latitudeOffset, int $population = 5_000): \App\Entity\City
    {
        return CityFactory::createOne([
            'name' => $name,
            'latitude' => self::LATITUDE + $latitudeOffset,
            'longitude' => self::LONGITUDE,
            'population' => $population,
            'country' => $this->france,
        ]);
    }

    private function countrylessPlace(string $name, ?string $town, ?float $latitude = self::LATITUDE): Place
    {
        return PlaceFactory::createOne([
            'name' => $name,
            'cityName' => $town,
            'cityPostalCode' => null,
            'latitude' => $latitude,
            'longitude' => null === $latitude ? null : self::LONGITUDE,
            'city' => null,
            'country' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:places:locate-countryless'));
        $tester->execute($input);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester;
    }
}
