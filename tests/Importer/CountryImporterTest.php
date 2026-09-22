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
}
