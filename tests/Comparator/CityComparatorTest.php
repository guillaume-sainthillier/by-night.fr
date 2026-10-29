<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Comparator;

use App\Comparator\CityComparator;
use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Entity\City;
use App\Entity\Country;
use App\Factory\CityAliasFactory;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\ZipCityFactory;
use App\Tests\AppKernelTestCase;
use Override;

/**
 * Namesakes are told apart by the postal code, then the population; a name borne by a
 * single city is enough.
 */
final class CityComparatorTest extends AppKernelTestCase
{
    private Country $france;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->france = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
    }

    public function testTheCityOfThePostalCodeWinsOverAnEarlierNamesake(): void
    {
        $hamlet = $this->city('Pau', '73', 0);
        $prefecture = $this->city('Pau', '64', 82_697, ['64000', '64001 CEDEX']);

        self::assertSame($prefecture->getId(), $this->resolve([$hamlet, $prefecture], 'Pau', '64000'));
        self::assertSame($prefecture->getId(), $this->resolve([$hamlet, $prefecture], 'Pau', '64001'), 'A CEDEX code is the city\'s too');
    }

    public function testAHamletIsToldApartByItsDepartment(): void
    {
        $hamlet = $this->city('Pau', '73', 0);
        $prefecture = $this->city('Pau', '64', 82_697, ['64000']);

        self::assertSame($hamlet->getId(), $this->resolve([$prefecture, $hamlet], 'Pau', '73100'));
    }

    public function testAPostalCodeOfTheDepartmentWinsOverABiggerCityElsewhere(): void
    {
        $small = $this->city('Valence', '16', 247);
        $big = $this->city('Valence', '26', 64_726, ['26000']);

        self::assertSame($big->getId(), $this->resolve([$small, $big], 'Valence', '26500'));
        self::assertSame($small->getId(), $this->resolve([$big, $small], 'Valence', '16460'));
    }

    public function testCorsicanPostalCodesStartWith20(): void
    {
        $mainland = $this->city('Sainte-Lucie', '64', 5_000);
        $corsica = $this->city('Sainte-Lucie', '2A', 1_000);

        self::assertSame($corsica->getId(), $this->resolve([$mainland, $corsica], 'Sainte-Lucie', '20144'));
    }

    public function testWithoutAPostalCodeTheMostPopulatedNamesakeWins(): void
    {
        $hamlet = $this->city('Pau', '73', 0);
        $prefecture = $this->city('Pau', '64', 82_697, ['64000']);

        self::assertSame($prefecture->getId(), $this->resolve([$hamlet, $prefecture], 'Pau', null));
    }

    public function testANameBorneByASingleCityIsEnough(): void
    {
        $toulouse = $this->city('Toulouse', '31', 471_941, ['31000']);

        self::assertSame($toulouse->getId(), $this->resolve([$toulouse], 'Toulouse', '75001'), 'The postal code only tells namesakes apart');
    }

    public function testAnotherNameMatchesNothing(): void
    {
        self::assertNull($this->resolve([$this->city('Toulouse', '31', 471_941)], 'Tournefeuille', '31170'));
    }

    public function testThePostalCodeOfASingleCityLocatesATownNoCityIsNamed(): void
    {
        $paris = $this->city('Paris', '75', 2_138_551, ['75018']);
        $other = $this->city('Montmartre', '75', 0);

        self::assertSame($paris->getId(), $this->resolve([$other, $paris], 'PARIS 18EME', '75018'));
    }

    public function testAPostalCodeSharedByTwoCitiesLocatesNothing(): void
    {
        $doue = $this->city('Doué-en-Anjou', '49', 11_000, ['49700']);
        $gennes = $this->city('Gennes-Val-de-Loire', '49', 7_000, ['49700']);

        self::assertNull($this->resolve([$doue, $gennes], 'Tuffalun', '49700'));
        self::assertNull($this->resolve([$doue], 'Tuffalun', null), 'Nor does a missing one');
    }

    public function testACommuneNoCityIsNamedLeadsToItsTown(): void
    {
        $pire = $this->city('Piré-sur-Seiche', '35', 2_065, ['35150']);
        $janze = $this->city('Janzé', '35', 8_000, ['35150']);
        CityAliasFactory::createOne(['city' => $pire, 'name' => 'Piré-Chancé']);

        self::assertSame($pire->getId(), $this->resolve([$pire, $janze], 'Piré-Chancé', '35150'), 'The postal code is shared: the alias tells');
        self::assertSame($pire->getId(), $this->resolve([], 'PIRE CHANCE', null), 'Written any way the sources write it');
    }

    public function testAnArrondissementLeadsToItsCity(): void
    {
        $paris = $this->city('Paris', '75', 2_138_551, ['75009']);
        $quarter = $this->city('Paris 09 Opéra', '75', 0, ['75009']);
        CityAliasFactory::createOne(['city' => $paris, 'name' => 'Paris 9ème']);

        self::assertSame($paris->getId(), $this->resolve([$paris, $quarter], 'PARIS 9EME', '75009'));
    }

    public function testAnAliasReadsSpacesAsHyphens(): void
    {
        $bourg = $this->city('Bourg-Saint-Maurice', '73', 7_000, ['73700']);
        $seez = $this->city('Séez', '73', 2_000, ['73700']);
        CityAliasFactory::createOne(['city' => $bourg, 'name' => 'Bourg-Saint-Maurice']);

        self::assertSame($bourg->getId(), $this->resolve([$bourg, $seez], 'Bourg Saint Maurice', '73700'), 'The city\'s name is compared hyphens and all');
    }

    public function testACityBearingTheNameWinsOverAnAlias(): void
    {
        $town = $this->city('Saint-Ouen', '93', 50_000, ['93400']);
        $hamlet = $this->city('Saint-Ouen-sur-Seine', '41', 0);
        CityAliasFactory::createOne(['city' => $town, 'name' => 'Saint-Ouen-sur-Seine']);

        self::assertSame($hamlet->getId(), $this->resolve([$town, $hamlet], 'Saint-Ouen-sur-Seine', '93400'), 'Aliases only come once no city bears the name');
    }

    public function testThePostalCodeOfASingleCityWinsOverAnAlias(): void
    {
        $village = $this->city('Lezé', '35', 0, ['35680']);
        $pire = $this->city('Piré-sur-Seiche', '35', 2_065, ['35150']);
        CityAliasFactory::createOne(['city' => $pire, 'name' => 'Piré-Chancé']);

        self::assertSame($village->getId(), $this->resolve([$village, $pire], 'Piré-Chancé', '35680'), 'As before the aliases');
    }

    public function testAnAliasOfAnotherDepartmentIsNoMatch(): void
    {
        $pire = $this->city('Piré-sur-Seiche', '35', 2_065, ['35150']);
        CityAliasFactory::createOne(['city' => $pire, 'name' => 'Piré-Chancé']);

        self::assertNull($this->resolve([], 'Piré-Chancé', '73700'));
    }

    public function testAnAliasOfAnotherCountryIsNoMatch(): void
    {
        $belgium = CountryFactory::createOne(['id' => 'BE', 'name' => 'Belgique']);
        $bastogne = CityFactory::createOne(['name' => 'Bastogne', 'country' => $belgium]);
        CityAliasFactory::createOne(['city' => $bastogne, 'name' => 'Bertogne']);

        self::assertNull($this->resolve([], 'Bertogne', null));
    }

    public function testNamesakesInTwoDepartmentsAreTwoCitiesOfABatch(): void
    {
        self::assertNotSame($this->dto('Pau', '64000')->getUniqueKey(), $this->dto('Pau', '73100')->getUniqueKey());
        self::assertSame($this->dto('Pau', '64000')->getUniqueKey(), $this->dto('Pau', '64000')->getUniqueKey());
    }

    /**
     * @param list<string> $postalCodes
     */
    private function city(string $name, string $department, int $population, array $postalCodes = []): City
    {
        $city = CityFactory::createOne(['name' => $name, 'admin2Code' => $department, 'population' => $population, 'country' => $this->france]);
        foreach ($postalCodes as $postalCode) {
            ZipCityFactory::createOne(['parent' => $city, 'postalCode' => $postalCode, 'country' => $this->france]);
        }

        return $city;
    }

    /**
     * @param list<City> $candidates
     */
    private function resolve(array $candidates, string $name, ?string $postalCode): ?int
    {
        // A fresh kernel: the zip codes are read from the database, not from the collections the
        // factories left empty
        self::bootKernel();
        $candidates = array_map(static fn (City $city): City => CityFactory::find(['id' => $city->getId()]), $candidates);

        $city = self::getContainer()->get(CityComparator::class)->getMostMatching($candidates, $this->dto($name, $postalCode))?->getEntity();

        return $city instanceof City ? $city->getId() : null;
    }

    private function dto(string $name, ?string $postalCode): CityDto
    {
        $country = new CountryDto();
        $country->entityId = 'FR';
        $country->code = 'FR';

        $dto = new CityDto();
        $dto->name = $name;
        $dto->postalCode = $postalCode;
        $dto->country = $country;

        return $dto;
    }
}
