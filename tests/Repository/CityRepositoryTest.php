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
        self::assertSame(['FR', '31000', '01000'], array_values($query['params']));
        self::assertSame(
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
            array_values($query['types']),
        );
    }

    private function createCityDto(string $postalCode): CityDto
    {
        $country = new CountryDto();
        $country->entityId = 'FR';

        $city = new CityDto();
        $city->postalCode = $postalCode;
        $city->country = $country;

        return $city;
    }
}
