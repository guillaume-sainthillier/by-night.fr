<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Manager;

use App\Entity\City;
use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\PlaceFactory;
use App\Manager\PreloadManager;
use App\Tests\AppKernelTestCase;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\ORM\EntityManagerInterface;

final class PreloadManagerTest extends AppKernelTestCase
{
    /**
     * A city sits in the identity map under its inheritance root (AdminZone): looked up under
     * City, it was never found and every list page preloaded its own city once more.
     */
    public function testALoadedCityIsNotQueriedAgain(): void
    {
        $cityId = CityFactory::createOne()->getId();

        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        self::assertInstanceOf(BacktraceDebugDataHolder::class, $queries);
        $queries->reset();

        self::getContainer()->get(PreloadManager::class)->preloadEntities(City::class, [$cityId]);

        self::assertCount(0, $queries->getData()['default'] ?? []);
    }

    /**
     * find() hands back the proxy it finds in the identity map without loading it: a single
     * id to preload was left for a lazy load later on.
     */
    public function testALoneProxyIsLoaded(): void
    {
        $placeId = PlaceFactory::createOne()->getId();
        // A fresh kernel, so the place is only known through the reference below
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $place = $entityManager->getReference(Place::class, $placeId);

        self::getContainer()->get(PreloadManager::class)->preloadEntities(Place::class, [$placeId]);

        self::assertFalse($entityManager->getUnitOfWork()->isUninitializedObject($place));
    }
}
