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
use App\Message\PurgeCdnCacheUrl;
use App\MessageHandler\PurgeCdnCacheUrlHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Handler\Acknowledger;

final class PurgeCdnCacheUrlHandlerTest extends TestCase
{
    /** @var list<int> number of files carried by each Cloudflare request */
    private array $requestSizes = [];

    public function testFlushesOneCloudflareRequestPerHundredMessages(): void
    {
        $handler = self::makeHandler($this->makeClient());
        $acks = [];

        for ($i = 1; $i <= 100; ++$i) {
            $acks[] = $ack = new Acknowledger(PurgeCdnCacheUrlHandler::class);
            $pending = $handler(new PurgeCdnCacheUrl(\sprintf('/uploads/documents/%d.jpg', $i)), $ack);

            if ($i < CloudflareCdnPurger::MAX_FILES_PER_REQUEST) {
                self::assertSame($i, $pending, 'messages are held back until the batch is full');
                self::assertSame([], $this->requestSizes);
            }
        }

        self::assertSame([100], $this->requestSizes);
        foreach ($acks as $ack) {
            self::assertTrue($ack->isAcknowledged());
            self::assertNull($ack->getError());
        }
    }

    public function testCloudflareErrorsFailTheWholeBatch(): void
    {
        $client = new MockHttpClient(new MockResponse('{"success":false,"errors":[{"code":1,"message":"boom"}]}'));
        $handler = self::makeHandler($client);

        $first = new Acknowledger(PurgeCdnCacheUrlHandler::class);
        $second = new Acknowledger(PurgeCdnCacheUrlHandler::class);
        $handler(new PurgeCdnCacheUrl('/uploads/documents/1.jpg'), $first);
        $handler(new PurgeCdnCacheUrl('/uploads/documents/2.jpg'), $second);
        $handler->flush(true);

        foreach ([$first, $second] as $ack) {
            $error = $ack->getError();
            self::assertNotNull($error);
            self::assertStringContainsString('Cloudflare purge failed', $error->getMessage());
        }
    }

    private static function makeHandler(MockHttpClient $client): PurgeCdnCacheUrlHandler
    {
        return new PurgeCdnCacheUrlHandler(
            new CloudflareCdnPurger($client, 'zone-123', 'https://cdn.example.test'),
            new NullLogger(),
        );
    }

    private function makeClient(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = json_decode((string) $options['body'], true);
            self::assertIsArray($body);

            $this->requestSizes[] = \count($body['files']);

            return new MockResponse('{"success":true,"errors":[],"messages":[],"result":{"id":"x"}}');
        });
    }
}
