<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EventListener;

use App\EventListener\McpRateLimitListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class McpRateLimitListenerTest extends TestCase
{
    public function testAnAddressPastItsLimitIsToldWhenToComeBack(): void
    {
        $listener = $this->listener(limit: 2);

        self::assertNull($this->handle($listener, $this->request('10.0.0.1')));
        self::assertNull($this->handle($listener, $this->request('10.0.0.1')));
        $refused = $this->handle($listener, $this->request('10.0.0.1'));

        self::assertNotNull($refused);
        self::assertSame(429, $refused->getStatusCode());
        self::assertGreaterThan(0, (int) $refused->headers->get('Retry-After'));
        self::assertSame(-32000, json_decode((string) $refused->getContent(), true)['error']['code']);
        // Another address keeps its own count
        self::assertNull($this->handle($listener, $this->request('10.0.0.2')));
    }

    public function testOnlyTheMcpRequestsCount(): void
    {
        $listener = $this->listener(limit: 1);

        self::assertNull($this->handle($listener, $this->request('10.0.0.1', route: 'app_index')));
        self::assertNull($this->handle($listener, $this->request('10.0.0.1', method: 'GET')));
        self::assertNull($this->handle($listener, $this->request('10.0.0.1')));
    }

    private function listener(int $limit): McpRateLimitListener
    {
        return new McpRateLimitListener(new RateLimiterFactory(['id' => 'mcp', 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 minute'], new InMemoryStorage()));
    }

    private function request(string $ip, string $route = '_mcp_endpoint_default', string $method = 'POST'): Request
    {
        $request = Request::create('/mcp', $method, server: ['REMOTE_ADDR' => $ip]);
        $request->attributes->set('_route', $route);

        return $request;
    }

    /**
     * Each call consumes the limit: the same request is answered differently the next time.
     *
     * @phpstan-impure
     */
    private function handle(McpRateLimitListener $listener, Request $request): ?Response
    {
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        return $event->getResponse();
    }
}
