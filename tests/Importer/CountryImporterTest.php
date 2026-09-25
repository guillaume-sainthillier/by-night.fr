<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Importer;

use App\Factory\CountryFactory;
use App\Factory\ZipCityFactory;
use App\Importer\CountryImporter;
use App\Tests\AppKernelTestCase;
use ReflectionMethod;

use function Zenstruck\Foundry\Persistence\save;

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
        // A fresh kernel, whose entity manager does not know $france: what the import loops'
        // clear() does every few hundred rows
        self::bootKernel();

        $country = new ReflectionMethod(CountryImporter::class, 'managedCountry')
            ->invoke(self::getContainer()->get(CountryImporter::class), $france);
        // Built unsaved then saved as is: persisting through the factory would swap a detached
        // country for its managed instance itself, hiding the failure
        save(ZipCityFactory::new()->withoutPersisting()->create(['postalCode' => '31000', 'country' => $country, 'parent' => null]));

        self::assertSame(1, ZipCityFactory::count(['postalCode' => '31000', 'country' => 'FR']));
    }
}
