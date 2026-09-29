<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Entity;

use App\Entity\AdminZone1;
use App\Entity\AdminZone2;
use App\Entity\City;
use App\Entity\Country;
use App\Tests\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The geography used to be readOnly, which lost its flush optimisation when it became editable
 * from the back-office: this guards its replacement, the explicit change tracking.
 */
final class GeographyChangeTrackingTest extends AppKernelTestCase
{
    /**
     * @param class-string $entityClass
     */
    #[DataProvider('provideGeographyEntities')]
    public function testOnlyAnExplicitPersistWritesTheGeography(string $entityClass): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        self::assertTrue($entityManager->getClassMetadata($entityClass)->isChangeTrackingDeferredExplicit());
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideGeographyEntities(): iterable
    {
        yield 'country' => [Country::class];
        yield 'city' => [City::class];
        yield 'region' => [AdminZone1::class];
        yield 'department' => [AdminZone2::class];
    }
}
