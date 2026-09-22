<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Entity\Event;
use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\ZipCityFactory;
use App\Tests\AppKernelTestCase;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class PlacesBackfillCountriesCommandTest extends AppKernelTestCase
{
    private Place $toulousePlace;

    private Place $blagnacPlace;

    private Place $swissOrBelgianPlace;

    private Place $unknownPostalCodePlace;

    private Event $eventWithoutCountry;

    private Event $eventWithCountry;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $france = CountryFactory::france()->create();
        $switzerland = CountryFactory::switzerland()->create();
        $belgium = CountryFactory::belgium()->create();

        $toulouse = CityFactory::createOne(['name' => 'Toulouse', 'country' => $france]);
        ZipCityFactory::createOne(['name' => 'Toulouse', 'postalCode' => '31000', 'country' => $france, 'parent' => $toulouse]);
        // 1000 is both Lausanne and Bruxelles: the postal code alone cannot name the country
        ZipCityFactory::createOne(['name' => 'Lausanne', 'postalCode' => '1000', 'country' => $switzerland, 'parent' => CityFactory::createOne(['name' => 'Lausanne', 'country' => $switzerland])]);
        ZipCityFactory::createOne(['name' => 'Bruxelles', 'postalCode' => '1000', 'country' => $belgium, 'parent' => CityFactory::createOne(['name' => 'Bruxelles', 'country' => $belgium])]);

        $noLocation = ['city' => null, 'country' => null];
        $this->toulousePlace = PlaceFactory::createOne(['name' => 'Le Bikini', 'cityName' => 'TOULOUSE', 'cityPostalCode' => '31000', ...$noLocation]);
        $this->blagnacPlace = PlaceFactory::createOne(['name' => 'Aéroport', 'cityName' => 'Blagnac', 'cityPostalCode' => '31000', ...$noLocation]);
        $this->swissOrBelgianPlace = PlaceFactory::createOne(['name' => 'Gare', 'cityName' => 'Lausanne', 'cityPostalCode' => '1000', ...$noLocation]);
        $this->unknownPostalCodePlace = PlaceFactory::createOne(['name' => 'Nulle part', 'cityName' => 'Ailleurs', 'cityPostalCode' => '99999', ...$noLocation]);

        $this->eventWithoutCountry = EventFactory::createOne(['place' => $this->toulousePlace, 'placeCountry' => null]);
        $this->eventWithCountry = EventFactory::createOne(['place' => $this->toulousePlace, 'placeCountry' => $france]);
    }

    public function testDryRunReportsWithoutWriting(): void
    {
        $output = $this->executeBackfill(dryRun: true);

        self::assertStringContainsString('Nothing was written', $output);
        self::assertMatchesRegularExpression('/repaired\s+2\b/', $output);
        self::assertMatchesRegularExpression('/events updated\s+1\b/', $output, 'The preview counts the events the repair would touch');
        self::assertSame(4, PlaceFactory::count(['country' => null]), 'A preview leaves every place as it was');
        self::assertSame(1, EventFactory::count(['placeCountry' => null]));
    }

    public function testPostalCodeNamesTheCountryAndTheCityNameTheCity(): void
    {
        $output = $this->executeBackfill(dryRun: false);

        self::assertStringContainsString('2 places repaired', $output);

        $toulousePlace = PlaceFactory::find($this->toulousePlace->getId());
        self::assertSame('FR', $toulousePlace->getCountry()?->getId());
        self::assertSame('Toulouse', $toulousePlace->getCity()?->getName(), 'Matched despite the upper-cased stored name');

        $blagnacPlace = PlaceFactory::find($this->blagnacPlace->getId());
        self::assertSame('FR', $blagnacPlace->getCountry()?->getId(), 'The postal code is enough for the country');
        self::assertNull($blagnacPlace->getCity(), 'No zip_city row of 31000 is named Blagnac, so no guess');

        self::assertNull(PlaceFactory::find($this->swissOrBelgianPlace->getId())->getCountry(), 'A postal code shared by two countries is left alone');
        self::assertNull(PlaceFactory::find($this->unknownPostalCodePlace->getId())->getCountry(), 'An unknown postal code is left alone');

        self::assertSame('FR', EventFactory::find($this->eventWithoutCountry->getId())->getPlaceCountry()?->getId(), 'Events of a repaired place get its country');
        self::assertSame('FR', EventFactory::find($this->eventWithCountry->getId())->getPlaceCountry()?->getId());
        self::assertSame(0, EventFactory::count(['placeCountry' => null]));
    }

    private function executeBackfill(bool $dryRun): string
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:places:backfill-countries'));
        $tester->execute($dryRun ? ['--dry-run' => true] : []);
        $tester->assertCommandIsSuccessful();

        return $tester->getDisplay();
    }
}
