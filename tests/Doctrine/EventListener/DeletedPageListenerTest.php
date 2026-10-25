<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EventListener;

use App\Enum\DeletedPageKind;
use App\Factory\CityFactory;
use App\Factory\DeletedPageFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\PublisherFactory;
use App\Tests\AppKernelTestCase;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\flush_after;

/**
 * The publishers and venues deleted leave their page behind, which answers 410 Gone.
 */
final class DeletedPageListenerTest extends AppKernelTestCase
{
    public function testADeletedPublisherLeavesItsSlug(): void
    {
        delete(PublisherFactory::createOne(['slug' => 'le-collectif']));

        self::assertSame(1, DeletedPageFactory::count(['kind' => DeletedPageKind::Publisher, 'identifier' => 'le-collectif']));
    }

    public function testADeletedVenueLeavesItsSlugInItsLocation(): void
    {
        $toulouse = CityFactory::toulouse()->create();

        delete(PlaceFactory::createOne(['slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]));

        self::assertSame(1, DeletedPageFactory::count(['kind' => DeletedPageKind::Place, 'identifier' => 'toulouse/le-bikini']));
    }

    public function testASlugDeletedAgainIsRecordedOnce(): void
    {
        delete(PublisherFactory::createOne(['slug' => 'le-collectif']));
        delete(PublisherFactory::createOne(['slug' => 'le-collectif']));

        self::assertSame(1, DeletedPageFactory::count(['identifier' => 'le-collectif']));
    }

    public function testTwoVenuesOfOneSlugDeletedInOneFlushAreRecordedOnce(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $first = PlaceFactory::createOne(['slug' => 'salle-des-fetes', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $second = PlaceFactory::createOne(['slug' => 'salle-des-fetes', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        flush_after(static function () use ($first, $second): void {
            delete($first);
            delete($second);
        });

        self::assertSame(1, DeletedPageFactory::count(['identifier' => 'toulouse/salle-des-fetes']));
    }

    /**
     * Events are found by id: an id up to the highest is a deleted one, no record needed.
     */
    public function testADeletedEventLeavesNothing(): void
    {
        delete(EventFactory::createOne());

        self::assertSame(0, DeletedPageFactory::count());
    }
}
