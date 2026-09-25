<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Repository;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Entity\City;
use App\Factory\AdminZone1Factory;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\ZipCityFactory;
use App\Repository\CityRepository;
use App\Tests\AppKernelTestCase;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\DBAL\ParameterType;

final class CityRepositoryTest extends AppKernelTestCase
{
    /**
     * Array keys turn "31000" into an int while "01000" stays a string. Bound as integers,
     * MySQL compares postal_code numerically (its index unusable) and SQLite loses the
     * leading-zero code ('01000' is not 1000 as text): both symptoms are locked here.
     */
    public function testPostalCodesAreBoundAsStrings(): void
    {
        $france = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        $toulouse = CityFactory::createOne(['name' => 'Toulouse', 'country' => $france]);
        $bourg = CityFactory::createOne(['name' => 'Bourg-en-Bresse', 'country' => $france]);
        ZipCityFactory::createOne(['name' => 'Toulouse', 'postalCode' => '31000', 'country' => $france, 'parent' => $toulouse]);
        ZipCityFactory::createOne(['name' => 'Bourg-en-Bresse', 'postalCode' => '01000', 'country' => $france, 'parent' => $bourg]);

        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        self::assertInstanceOf(BacktraceDebugDataHolder::class, $queries);
        $queries->reset();

        $cities = self::getContainer()->get(CityRepository::class)->findAllByDtos([
            $this->createCityDto('31000'),
            $this->createCityDto('01000'),
        ], false);

        self::assertEqualsCanonicalizing(
            [$toulouse->getId(), $bourg->getId()],
            array_map(static fn ($city) => $city->getId(), $cities),
        );

        $query = $queries->getData()['default'][0] ?? null;
        self::assertNotNull($query);
        self::assertSame(['FR', '31000', '01000', 'FR'], array_values($query['params']));
        self::assertSame(array_fill(0, 4, ParameterType::STRING), array_values($query['types']));
    }

    public function testCitiesFoundByNameAndByPostalCodeAreMergedOnce(): void
    {
        $france = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        $toulouse = CityFactory::createOne(['name' => 'Toulouse', 'country' => $france]);
        $bourg = CityFactory::createOne(['name' => 'Bourg-en-Bresse', 'country' => $france]);
        ZipCityFactory::createOne(['name' => 'Toulouse', 'postalCode' => '31000', 'country' => $france, 'parent' => $toulouse]);
        ZipCityFactory::createOne(['name' => 'Bourg-en-Bresse', 'postalCode' => '01000', 'country' => $france, 'parent' => $bourg]);

        $cities = self::getContainer()->get(CityRepository::class)->findAllByDtos([
            // Found by both its name and its postal code: listed once
            $this->createCityDto('31000', 'Toulouse'),
            // Found by its postal code only
            $this->createCityDto('01000', 'Bourg'),
        ], false);

        self::assertEqualsCanonicalizing(
            [$toulouse->getId(), $bourg->getId()],
            array_map(static fn ($city) => $city->getId(), $cities),
        );
    }

    public function testCitiesAreLoadedWithTheirParentWithoutAQueryEach(): void
    {
        $france = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        $occitanie = AdminZone1Factory::createOne(['name' => 'Occitanie', 'country' => $france]);
        $toulouse = CityFactory::createOne(['name' => 'Toulouse', 'country' => $france, 'parent' => $occitanie]);
        $albi = CityFactory::createOne(['name' => 'Albi', 'country' => $france, 'parent' => $occitanie]);
        ZipCityFactory::createOne(['name' => 'Albi', 'postalCode' => '81000', 'country' => $france, 'parent' => $albi]);
        // A fresh kernel, so nothing comes from the identity map: every row is hydrated from the lookup
        self::bootKernel();

        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        self::assertInstanceOf(BacktraceDebugDataHolder::class, $queries);
        $queries->reset();

        $cities = self::getContainer()->get(CityRepository::class)->findAllByDtos([
            $this->createCityDto('31000', 'Toulouse'),
            $this->createCityDto('81000'),
        ], false);
        $parents = array_map(static fn (City $city): ?string => $city->getParent()?->getName(), $cities);

        self::assertEqualsCanonicalizing([$toulouse->getId(), $albi->getId()], array_map(static fn (City $city) => $city->getId(), $cities));
        self::assertSame(['Occitanie', 'Occitanie'], $parents);
        // One query per criterion, the parents joined: none loaded row by row. The parent is
        // an admin_zone inheritance root, which Doctrine cannot proxy, so a missing join
        // would show as one "WHERE t0.id = ?" query per city.
        self::assertCount(2, $queries->getData()['default'] ?? []);
    }

    private function createCityDto(string $postalCode, ?string $name = null): CityDto
    {
        $country = new CountryDto();
        $country->entityId = 'FR';

        $city = new CityDto();
        $city->name = $name;
        $city->postalCode = $postalCode;
        $city->country = $country;

        return $city;
    }
}
