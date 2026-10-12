<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Manager;

use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\PlaceLegacySlugFactory;
use App\Factory\PlaceMetadataFactory;
use App\Factory\PlaceNameSlugFactory;
use App\Manager\PlaceMerger;
use App\Tests\AppKernelTestCase;
use InvalidArgumentException;

final class PlaceMergerTest extends AppKernelTestCase
{
    public function testTheTargetTakesTheEventsIdentitiesAndSlugsOfTheOthers(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $blagnac = CityFactory::createOne(['name' => 'Blagnac', 'country' => $toulouse->getCountry()]);
        $zenith = PlaceFactory::createOne(['name' => 'Zénith Toulouse Métropole', 'slug' => 'zenith-toulouse-metropole', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $duplicate = PlaceFactory::createOne(['name' => 'Zénith', 'slug' => 'zenith', 'city' => $blagnac, 'country' => $blagnac->getCountry()]);
        $duplicateId = $duplicate->getId();
        $events = EventFactory::createMany(2, ['place' => $duplicate]);
        PlaceMetadataFactory::createOne(['place' => $duplicate, 'externalId' => 'oa-42']);
        PlaceNameSlugFactory::createOne(['place' => $duplicate, 'slug' => 'zenith', 'city' => $blagnac]);
        // A place merged into the duplicate earlier now leads to the target
        PlaceLegacySlugFactory::createOne(['place' => $duplicate, 'slug' => 'le-zenith', 'city' => $blagnac, 'country' => $blagnac->getCountry()]);

        $result = self::getContainer()->get(PlaceMerger::class)->merge($zenith, [$zenith, $duplicate]);

        self::assertSame(['places' => 1, 'events' => 2, 'identities' => 1, 'slugs' => 3], $result);
        self::assertSame(0, PlaceFactory::count(['id' => $duplicateId]));
        foreach ($events as $event) {
            self::assertSame($zenith->getId(), EventFactory::find(['id' => $event->getId()])->getPlace()?->getId());
        }

        self::assertSame($zenith->getId(), PlaceMetadataFactory::find(['externalId' => 'oa-42'])->getPlace()?->getId());
        self::assertSame($zenith->getId(), PlaceNameSlugFactory::find(['slug' => 'zenith'])->getPlace()?->getId());
        $legacySlug = PlaceLegacySlugFactory::find(['slug' => 'zenith']);
        self::assertSame($zenith->getId(), $legacySlug->getPlace()->getId());
        self::assertSame($blagnac->getId(), $legacySlug->getCity()?->getId());
        self::assertSame($zenith->getId(), PlaceLegacySlugFactory::find(['slug' => 'le-zenith'])->getPlace()->getId());
    }

    public function testASlugTheTargetAlreadyAnswersToIsNotKept(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry(), 'street' => null]);
        $duplicate = PlaceFactory::createOne(['slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry(), 'street' => 'Rue Hermès']);
        PlaceNameSlugFactory::createOne(['place' => $bikini, 'slug' => 'bikini', 'city' => $toulouse]);
        PlaceNameSlugFactory::createOne(['place' => $duplicate, 'slug' => 'bikini', 'city' => $toulouse]);

        $result = self::getContainer()->get(PlaceMerger::class)->merge($bikini, [$duplicate]);

        self::assertSame(['places' => 1, 'events' => 0, 'identities' => 0, 'slugs' => 0], $result);
        self::assertSame(0, PlaceLegacySlugFactory::count());
        self::assertSame(1, PlaceNameSlugFactory::count(['slug' => 'bikini']));
        // The address the target lacked comes from the place merged into it
        self::assertSame('Rue Hermès', PlaceFactory::find(['id' => $bikini->getId()])->getStreet());
    }

    public function testThePlaceWithTheMostEventsIsSuggested(): void
    {
        $few = PlaceFactory::createOne();
        $many = PlaceFactory::createOne();
        $none = PlaceFactory::createOne();

        $merger = self::getContainer()->get(PlaceMerger::class);

        self::assertSame($many, $merger->suggestTarget([$few, $many, $none], [$few->getId() => 1, $many->getId() => 3]));
        // A tie goes to the oldest
        self::assertSame($few, $merger->suggestTarget([$none, $few], []));
    }

    public function testAPlaceIsNotMergedIntoItself(): void
    {
        $place = PlaceFactory::createOne();

        $this->expectException(InvalidArgumentException::class);

        self::getContainer()->get(PlaceMerger::class)->merge($place, [$place]);
    }
}
