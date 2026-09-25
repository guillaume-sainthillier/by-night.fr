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
use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\PlaceFactory;
use App\Factory\PlaceNameSlugFactory;
use App\Factory\ZipCityFactory;
use App\Tests\AppKernelTestCase;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The places filed under the wrong namesake of their city move to the namesake of their
 * postal code, with their name slugs; the others stay where they are.
 */
final class PlacesFixNamesakeCitiesCommandTest extends AppKernelTestCase
{
    private City $hamlet;

    private City $prefecture;

    private Place $misfiled;

    private Place $wellFiled;

    private Place $unique;

    private Place $nowhere;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $country = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        $this->hamlet = CityFactory::createOne(['name' => 'Pau', 'admin2Code' => '73', 'population' => 0, 'country' => $country]);
        $this->prefecture = CityFactory::createOne(['name' => 'Pau', 'admin2Code' => '64', 'population' => 82_697, 'country' => $country]);
        ZipCityFactory::createOne(['parent' => $this->prefecture, 'postalCode' => '64000', 'country' => $country]);
        $toulouse = CityFactory::createOne(['name' => 'Toulouse', 'admin2Code' => '31', 'country' => $country]);

        // Filed under the hamlet by the first-namesake-wins lookup
        $this->misfiled = PlaceFactory::createOne(['name' => 'Zénith de Pau', 'city' => $this->hamlet, 'cityPostalCode' => '64000', 'country' => $country]);
        PlaceNameSlugFactory::createOne(['place' => $this->misfiled, 'slug' => 'zenith', 'city' => $this->hamlet, 'country' => $country]);
        // Rightly in the hamlet
        $this->wellFiled = PlaceFactory::createOne(['name' => 'Salle du hameau', 'city' => $this->hamlet, 'cityPostalCode' => '73100', 'country' => $country]);
        // A city without namesake is left alone, whatever its postal code says
        $this->unique = PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'cityPostalCode' => '31400', 'country' => $country]);
        // Neither Pau is of the Gironde: the place stays rather than move between two wrong cities
        $this->nowhere = PlaceFactory::createOne(['name' => 'Salle égarée', 'city' => $this->hamlet, 'cityPostalCode' => '33000', 'country' => $country]);
    }

    public function testPreviewMovesNothing(): void
    {
        $display = $this->doRunCommand([])->getDisplay();

        self::assertStringContainsString('Previewing 3 place(s) on a city with namesakes', $display);
        self::assertStringContainsString('Zénith de Pau', $display);
        self::assertStringContainsString('1 place(s) would move', $display);
        self::assertSame($this->hamlet->getId(), PlaceFactory::find(['id' => $this->misfiled->getId()])->getCity()?->getId());
    }

    public function testApplyMovesTheMisfiledPlacesWithTheirNameSlugs(): void
    {
        $display = $this->doRunCommand(['--apply' => true])->getDisplay();

        self::assertStringContainsString('1 place(s) moved', $display);
        self::assertSame($this->prefecture->getId(), PlaceFactory::find(['id' => $this->misfiled->getId()])->getCity()?->getId());
        self::assertSame($this->prefecture->getId(), PlaceNameSlugFactory::find(['slug' => 'zenith'])->getCity()?->getId());
        self::assertSame($this->hamlet->getId(), PlaceFactory::find(['id' => $this->wellFiled->getId()])->getCity()?->getId());
        self::assertSame('Toulouse', PlaceFactory::find(['id' => $this->unique->getId()])->getCity()?->getName());
        self::assertSame($this->hamlet->getId(), PlaceFactory::find(['id' => $this->nowhere->getId()])->getCity()?->getId());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:places:fix-namesake-cities'));
        $tester->execute($input);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester;
    }
}
