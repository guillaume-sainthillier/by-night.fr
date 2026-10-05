<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\MessageHandler;

use App\Cdn\CloudflareCdnPurger;
use App\Message\PurgeCdnCacheTags;
use App\MessageHandler\PurgeCdnCacheTagsHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Handler\Acknowledger;

final class PurgeCdnCacheTagsHandlerTest extends TestCase
{
    /** @var list<list<string>> tags carried by each Cloudflare request */
    private array $requests = [];

    public function testPacksConsecutiveMessagesIntoOneRequest(): void
    {
        $handler = $this->makeHandler($this->makeClient());

        $first = new Acknowledger(PurgeCdnCacheTagsHandler::class);
        $second = new Acknowledger(PurgeCdnCacheTagsHandler::class);
        self::assertSame(1, $handler(new PurgeCdnCacheTags(['event-1', 'place-1']), $first));
        self::assertSame(2, $handler(new PurgeCdnCacheTags(['event-2', 'place-1']), $second), 'held back until the request is full');
        $handler->flush(true);

        self::assertSame([['event-1', 'place-1', 'event-2']], $this->requests, 'a tag queued twice is purged once');
        self::assertTrue($first->isAcknowledged());
        self::assertTrue($second->isAcknowledged());
    }

    public function testNeverSplitsAMessageAcrossTwoRequests(): void
    {
        $handler = $this->makeHandler($this->makeClient());

        // 60 + 40 distinct tags fill a request: the first 60 are repeated, so 60 + 50 would not fit
        $first = new Acknowledger(PurgeCdnCacheTagsHandler::class);
        $handler(new PurgeCdnCacheTags(self::tags(0, 60)), $first);
        $handler(new PurgeCdnCacheTags(self::tags(0, 100)), new Acknowledger(PurgeCdnCacheTagsHandler::class));
        self::assertSame([], $this->requests);

        $last = new Acknowledger(PurgeCdnCacheTagsHandler::class);
        $handler(new PurgeCdnCacheTags(self::tags(100, 110)), $last);

        self::assertSame([self::tags(0, 100)], $this->requests, 'the pending tags go first');
        self::assertTrue($first->isAcknowledged());
        self::assertFalse($last->isAcknowledged());

        $handler->flush(true);
        self::assertSame([self::tags(0, 100), self::tags(100, 110)], $this->requests);
        self::assertTrue($last->isAcknowledged());
    }

    public function testCloudflareErrorsFailTheWholeBatch(): void
    {
        $handler = $this->makeHandler(new MockHttpClient(new MockResponse('{"success":false,"errors":[{"code":1,"message":"boom"}]}')));

        $first = new Acknowledger(PurgeCdnCacheTagsHandler::class);
        $second = new Acknowledger(PurgeCdnCacheTagsHandler::class);
        $handler(new PurgeCdnCacheTags(['event-1']), $first);
        $handler(new PurgeCdnCacheTags(['event-2']), $second);
        $handler->flush(true);

        foreach ([$first, $second] as $ack) {
            self::assertStringContainsString('Cloudflare purge failed', (string) $ack->getError()?->getMessage());
        }
    }

    /**
     * @return non-empty-list<string>
     */
    private static function tags(int $from, int $to): array
    {
        return array_map(static fn (int $i): string => "event-$i", range($from, $to - 1));
    }

    private function makeHandler(MockHttpClient $client): PurgeCdnCacheTagsHandler
    {
        return new PurgeCdnCacheTagsHandler(new CloudflareCdnPurger($client, $client, 'zone-123', 'https://cdn.example.test'), new NullLogger());
    }

    private function makeClient(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = json_decode((string) $options['body'], true);
            self::assertIsArray($body);
            self::assertLessThanOrEqual(CloudflareCdnPurger::MAX_FILES_PER_REQUEST, \count($body['tags']));

            $this->requests[] = $body['tags'];

            return new MockResponse('{"success":true,"errors":[],"messages":[],"result":{"id":"x"}}');
        });
    }
}
