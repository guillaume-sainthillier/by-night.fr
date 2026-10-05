<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\MessageHandler;

use App\Message\PurgeCdnCacheTag;
use App\Message\PurgeCdnCacheTags;
use App\MessageHandler\PurgeCdnCacheTagHandler;
use App\Tests\AppKernelTestCase;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class PurgeCdnCacheTagHandlerTest extends AppKernelTestCase
{
    public function testForwardsTheQueuedOneTagMessagesToTheCdnTransport(): void
    {
        $cdn = self::getContainer()->get('messenger.transport.cdn');
        self::assertInstanceOf(InMemoryTransport::class, $cdn);
        $handler = self::getContainer()->get(PurgeCdnCacheTagHandler::class);

        $acks = [];
        foreach (['event-1', 'place-1', 'event-1'] as $tag) {
            $acks[] = $ack = new Acknowledger(PurgeCdnCacheTagHandler::class);
            $handler(new PurgeCdnCacheTag($tag), $ack);
        }
        $handler->flush(true);

        self::assertEquals([new PurgeCdnCacheTags(['event-1', 'place-1'])], array_map(static fn ($envelope): object => $envelope->getMessage(), $cdn->getSent()));
        foreach ($acks as $ack) {
            self::assertTrue($ack->isAcknowledged());
        }
    }
}
