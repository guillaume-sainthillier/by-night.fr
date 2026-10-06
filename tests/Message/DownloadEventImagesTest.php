<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Message;

use App\Message\DownloadEventImages;
use PHPUnit\Framework\TestCase;

final class DownloadEventImagesTest extends TestCase
{
    public function testAMessageQueuedBeforeThePagesItLeavesToTheDownloadUnserializesWithNone(): void
    {
        // As the "image" transport stored it before $pageEventIds existed (PhpSerializer)
        $queued = \sprintf('O:%d:"%s":1:{s:8:"eventIds";a:1:{i:0;i:42;}}', \strlen(DownloadEventImages::class), DownloadEventImages::class);

        $message = unserialize($queued);

        self::assertInstanceOf(DownloadEventImages::class, $message);
        self::assertSame([42], $message->eventIds);
        self::assertSame([], $message->pageEventIds);
    }
}
