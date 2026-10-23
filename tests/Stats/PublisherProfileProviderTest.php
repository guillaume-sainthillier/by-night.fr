<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Stats;

use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\PublisherFactory;
use App\Stats\PublisherProfileProvider;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

final class PublisherProfileProviderTest extends AppKernelTestCase
{
    /**
     * Only its events online count: no draft, no event folded into another one's page, nothing of another publisher.
     */
    public function testTheFiguresAddUpItsEventsOnline(): void
    {
        $publisher = PublisherFactory::createOne();
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $soon = new DateTimeImmutable('+10 days');
        $canonical = EventFactory::createOne(['publisher' => $publisher, 'place' => $bikini, 'startDate' => $soon, 'endDate' => $soon, 'prices' => 'Gratuit', 'participations' => 4, 'interests' => 2]);
        $past = new DateTimeImmutable('-10 days');
        EventFactory::createOne(['publisher' => $publisher, 'place' => $bikini, 'startDate' => $past, 'endDate' => $past, 'prices' => '12 €', 'participations' => 1, 'interests' => 0]);
        EventFactory::createOne(['publisher' => $publisher, 'place' => $bikini, 'draft' => true, 'participations' => 50]);
        EventFactory::createOne(['publisher' => $publisher, 'place' => $bikini, 'duplicateOf' => $canonical, 'participations' => 50]);
        EventFactory::createOne(['place' => $bikini, 'participations' => 50]);

        $profile = self::getContainer()->get(PublisherProfileProvider::class)->get($publisher);

        self::assertSame(2, $profile->stats->events);
        self::assertSame(1, $profile->stats->upcomingEvents);
        self::assertSame(1, $profile->stats->freeEvents);
        self::assertSame(7, $profile->stats->getAudience());
        self::assertSame(1, $profile->stats->cities);
        self::assertSame(2, $profile->stats->busiestPlaceEvents);
        self::assertSame(['Le Bikini'], array_column($profile->places, 'name'));
        self::assertSame((int) $past->format('Y'), $profile->stats->firstYear);
    }
}
