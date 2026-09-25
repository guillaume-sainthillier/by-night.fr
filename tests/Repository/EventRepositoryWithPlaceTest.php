<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Repository;

use App\Factory\AdminZone1Factory;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;

/**
 * An event page builds its URL from the event's city: the event page and its widgets load them together.
 */
final class EventRepositoryWithPlaceTest extends AppKernelTestCase
{
    public function testTheEventComesWithItsPlaceAndCityInOneQuery(): void
    {
        $france = CountryFactory::france()->create();
        $occitanie = AdminZone1Factory::createOne(['name' => 'Occitanie', 'country' => $france]);
        $city = CityFactory::toulouse()->create(['country' => $france, 'parent' => $occitanie]);
        $place = PlaceFactory::createOne(['city' => $city, 'country' => $france]);
        $eventId = EventFactory::createOne(['place' => $place])->getId();
        // A fresh kernel, so nothing comes from the identity map
        self::bootKernel();

        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        self::assertInstanceOf(BacktraceDebugDataHolder::class, $queries);
        $queries->reset();

        $event = self::getContainer()->get(EventRepository::class)->findOneWithPlace((int) $eventId);

        self::assertNotNull($event);
        self::assertSame('toulouse', $event->getLocationSlug());
        self::assertSame('Occitanie', $event->getPlace()?->getCity()?->getParent()?->getName());
        // The city's parent targets the AdminZone inheritance root, which Doctrine cannot proxy:
        // left out of the joins, it would be loaded with a query of its own
        self::assertCount(1, $queries->getData()['default'] ?? []);
    }
}
