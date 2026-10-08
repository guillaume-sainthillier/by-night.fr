<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Sentry;

use App\Cdn\CdnPurgeQuotaExceededException;
use App\Sentry\BeforeSend;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\Event;
use Sentry\EventHint;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Throwable;

final class BeforeSendTest extends TestCase
{
    public function testDropsThePurgesRefusedForQuota(): void
    {
        $hint = EventHint::fromArray(['exception' => new CdnPurgeQuotaExceededException('30')]);

        self::assertNull(new BeforeSend()(Event::createEvent(), $hint));
    }

    /**
     * @return iterable<string, array{?Throwable}>
     */
    public static function provideReportedEvents(): iterable
    {
        // The worker's log once the retries are exhausted
        yield 'purge still refused after its last retry' => [new HandlerFailedException(new Envelope(new stdClass()), [new CdnPurgeQuotaExceededException(null)])];
        yield 'other recoverable exception' => [new RecoverableMessageHandlingException('Boom')];
        yield 'other exception' => [new RuntimeException('Boom')];
        yield 'no exception' => [null];
    }

    #[DataProvider('provideReportedEvents')]
    public function testKeepsTheOtherEvents(?Throwable $exception): void
    {
        $event = Event::createEvent();

        self::assertSame($event, new BeforeSend()($event, EventHint::fromArray(['exception' => $exception])));
    }
}
