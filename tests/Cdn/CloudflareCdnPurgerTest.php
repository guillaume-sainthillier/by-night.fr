<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Cdn;

use App\Cdn\CloudflareCdnPurger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ThrottlingHttpClient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CloudflareCdnPurgerTest extends TestCase
{
    private const string BASE_URI = 'https://api.cloudflare.com/client/v4/';

    /** @var list<array{method: string, url: string, files: list<string>}> */
    private array $requests = [];

    public function testSplitsPathsIntoRequestsOfAtMostHundredFiles(): void
    {
        $purger = self::makePurger($this->makeClient());

        $purger->purge(self::paths(250));

        self::assertCount(3, $this->requests);
        self::assertSame([100, 100, 50], array_map(static fn (array $request): int => \count($request['files']), $this->requests));
        self::assertSame('POST', $this->requests[0]['method']);
        self::assertSame(self::BASE_URI . 'zones/zone-123/purge_cache', $this->requests[0]['url']);
        self::assertSame('https://cdn.example.test/uploads/documents/0.jpg', $this->requests[0]['files'][0]);
        self::assertSame('https://cdn.example.test/uploads/documents/249.jpg', $this->requests[2]['files'][49]);
    }

    /**
     * Mirrors the production wiring: the scoped "cloudflare.client" is decorated by the
     * ThrottlingHttpClient, so every request (not every path) costs one quota token.
     */
    public function testEachRequestCostsOneTokenOfTheThrottledClient(): void
    {
        $limiter = new RateLimiterFactory([
            'id' => 'cloudflare_purge_test',
            'policy' => 'token_bucket',
            'limit' => 25,
            'rate' => ['interval' => '1 hour', 'amount' => 1],
        ], new InMemoryStorage());
        $purger = self::makePurger(new ThrottlingHttpClient($this->makeClient(), $limiter->create()));

        $purger->purge(self::paths(250));

        self::assertCount(3, $this->requests);
        self::assertSame(22, $limiter->create()->consume(0)->getRemainingTokens());
    }

    public function testSendsNothingForAnEmptyPathList(): void
    {
        self::makePurger($this->makeClient())->purge([]);

        self::assertSame([], $this->requests);
    }

    public function testRejectsUnsuccessfulAnswers(): void
    {
        $client = new MockHttpClient(new MockResponse('{"success":false,"errors":[{"code":1,"message":"nope"}]}'), self::BASE_URI);
        $purger = self::makePurger($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cloudflare purge failed');

        $purger->purge(['/uploads/documents/a.jpg']);
    }

    /**
     * The throttled client is the single source of truth for the quota, so a 429 is an
     * anomaly handled like any other HTTP error, not a signal to schedule around.
     */
    public function testHttpErrorsSurfaceAsClientExceptions(): void
    {
        $client = new MockHttpClient(new MockResponse('{"success":false,"errors":[{"code":10000,"message":"rate limited"}]}', [
            'http_code' => 429,
        ]), self::BASE_URI);
        $purger = self::makePurger($client);

        $this->expectException(ClientExceptionInterface::class);

        $purger->purge(['/uploads/documents/a.jpg']);
    }

    private function makeClient(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = json_decode((string) $options['body'], true);
            self::assertIsArray($body);

            $this->requests[] = [
                'method' => $method,
                'url' => $url,
                'files' => $body['files'],
            ];

            return new MockResponse('{"success":true,"errors":[],"messages":[],"result":{"id":"x"}}');
        }, self::BASE_URI);
    }

    private static function makePurger(HttpClientInterface $client): CloudflareCdnPurger
    {
        return new CloudflareCdnPurger($client, 'zone-123', 'https://cdn.example.test/');
    }

    /**
     * @return list<string>
     */
    private static function paths(int $count): array
    {
        return array_map(static fn (int $i): string => \sprintf('/uploads/documents/%d.jpg', $i), range(0, $count - 1));
    }
}
