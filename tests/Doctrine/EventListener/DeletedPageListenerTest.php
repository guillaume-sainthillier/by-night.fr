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
use App\Factory\UserFactory;
use App\Tests\AppKernelTestCase;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\flush_after;

/**
 * The events, members, publishers and venues deleted leave their page behind, which answers 410 Gone.
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

    public function testADeletedEventLeavesItsId(): void
    {
        $event = EventFactory::createOne();
        $id = (string) $event->getId();

        delete($event);

        self::assertSame(1, DeletedPageFactory::count(['kind' => DeletedPageKind::Event, 'identifier' => $id]));
    }

    public function testADeletedAccountLeavesItsId(): void
    {
        $member = UserFactory::createOne();
        $id = (string) $member->getId();

        delete($member);

        self::assertSame(1, DeletedPageFactory::count(['kind' => DeletedPageKind::User, 'identifier' => $id]));
    }

    /**
     * A cleanup deletes thousands of events in one flush, some of them perhaps recorded already.
     */
    public function testTheEventsDeletedInOneFlushAreRecordedOnceEach(): void
    {
        [$first, $second] = EventFactory::createMany(2);
        $ids = [(string) $first->getId(), (string) $second->getId()];
        DeletedPageFactory::createOne(['kind' => DeletedPageKind::Event, 'identifier' => $ids[0]]);

        flush_after(static function () use ($first, $second): void {
            delete($first);
            delete($second);
        });

        self::assertSame(1, DeletedPageFactory::count(['kind' => DeletedPageKind::Event, 'identifier' => $ids[0]]));
        self::assertSame(1, DeletedPageFactory::count(['kind' => DeletedPageKind::Event, 'identifier' => $ids[1]]));
    }
}
