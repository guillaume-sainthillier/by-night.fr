<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Importer;

use App\Entity\ZipCity;
use App\Factory\CountryFactory;
use App\Factory\ZipCityFactory;
use App\Importer\CountryImporter;
use App\Tests\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionMethod;

/**
 * The import itself downloads the GeoNames files: its queries are checked on their own.
 */
final class CountryImporterTest extends AppKernelTestCase
{
    public function testTheZipCitiesLeftWithoutACityAreDeleted(): void
    {
        $france = CountryFactory::france()->create();
        ZipCityFactory::createOne(['country' => $france, 'parent' => null, 'postalCode' => '99999']);

        new ReflectionMethod(CountryImporter::class, 'deleteEmptyDatas')
            ->invoke(self::getContainer()->get(CountryImporter::class), $france);

        self::assertSame(0, ZipCityFactory::count(['postalCode' => '99999']));
    }

    public function testRowsKeepTheirCountryAfterTheEntityManagerIsCleared(): void
    {
        $france = CountryFactory::france()->create();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // What the import loops do every few hundred rows
        $entityManager->clear();

        $country = new ReflectionMethod(CountryImporter::class, 'managedCountry')
            ->invoke(self::getContainer()->get(CountryImporter::class), $france);
        $zipCity = new ZipCity()
            ->setPostalCode('31000')
            ->setName('Toulouse')
            ->setAdmin1Code('76')
            ->setAdmin2Code('31')
            ->setLatitude(43.6)
            ->setLongitude(1.44)
            ->setCountry($country);
        $entityManager->persist($zipCity);
        $entityManager->flush();

        self::assertSame(1, ZipCityFactory::count(['postalCode' => '31000', 'country' => 'FR']));
    }
}
