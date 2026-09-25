<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EventListener;

use App\Factory\EventFactory;
use App\Factory\ParserDataFactory;
use App\Import\Firewall;
use App\Reject\Reject;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\flush_after;

/**
 * An imported event deleted on the site stays deleted: its exploration remembers it, and the
 * Firewall leaves the event out of the next imports of its source.
 */
final class EventParserDataListenerTest extends AppKernelTestCase
{
    public function testDeletingAnImportedEventRemembersItsExplorationAsDeleted(): void
    {
        $event = EventFactory::createOne([
            'externalId' => 'oa-1',
            'externalOrigin' => 'openagenda',
            'parserVersion' => '2.1',
            'externalUpdatedAt' => new DateTimeImmutable('2026-09-20 10:00:00'),
        ]);

        delete($event);

        $parserData = ParserDataFactory::find(['externalId' => 'oa-1', 'externalOrigin' => 'openagenda']);
        self::assertSame(Reject::EVENT_DELETED, $parserData->getReason());
        self::assertSame('2.1', $parserData->getParserVersion());
        self::assertSame(Firewall::VERSION, $parserData->getFirewallVersion());
        self::assertSame('2026-09-20 10:00:00', $parserData->getLastUpdated()?->format('Y-m-d H:i:s'));
    }

    public function testTheExistingExplorationIsMarkedDeleted(): void
    {
        ParserDataFactory::createOne(['externalId' => 'oa-2', 'externalOrigin' => 'openagenda', 'reason' => Reject::VALID]);
        $event = EventFactory::createOne(['externalId' => 'oa-2', 'externalOrigin' => 'openagenda']);

        delete($event);

        self::assertSame(1, ParserDataFactory::count(['externalId' => 'oa-2', 'externalOrigin' => 'openagenda', 'reason' => Reject::EVENT_DELETED]));
        self::assertSame(0, ParserDataFactory::count(['externalId' => 'oa-2', 'externalOrigin' => 'openagenda', 'reason' => Reject::VALID]));
    }

    public function testEventsDeletedTogetherAreAllRemembered(): void
    {
        $first = EventFactory::createOne(['externalId' => 'oa-3', 'externalOrigin' => 'openagenda']);
        $second = EventFactory::createOne(['externalId' => 'oa-4', 'externalOrigin' => 'openagenda']);

        flush_after(static function () use ($first, $second): void {
            delete($first);
            delete($second);
        });

        self::assertSame(2, ParserDataFactory::count(['externalOrigin' => 'openagenda', 'reason' => Reject::EVENT_DELETED]));
    }

    public function testAMemberEventLeavesNoExploration(): void
    {
        $before = ParserDataFactory::count();
        $event = EventFactory::createOne(['externalId' => null, 'externalOrigin' => null]);

        delete($event);

        self::assertSame($before, ParserDataFactory::count());
    }
}
