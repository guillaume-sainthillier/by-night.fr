<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EventSubscriber;

use App\Annotation\ReverseProxy;
use App\EventSubscriber\ReverseProxySubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ReverseProxySubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = ReverseProxySubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
        self::assertArrayHasKey(KernelEvents::CONTROLLER_ARGUMENTS, $events);
        self::assertEquals('onKernelResponse', $events[KernelEvents::RESPONSE]);
        self::assertEquals('onKernelControllerArguments', $events[KernelEvents::CONTROLLER_ARGUMENTS]);
    }

    public function testOnKernelControllerArgumentsSetsReverseProxyAttributeFromRequestAttributes(): void
    {
        $subscriber = new ReverseProxySubscriber(true);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        $reverseProxy = new ReverseProxy(ttl: 3600);
        $attributes = [$reverseProxy];

        // Set the attribute directly on the request (simulating what the framework does)
        $request->attributes->set('_reverse_proxy', $attributes);

        $event = new ControllerArgumentsEvent(
            $kernel,
            fn () => new Response(),
            [],
            $request,
            null
        );

        $subscriber->onKernelControllerArguments($event);

        // Should still be set
        self::assertTrue($request->attributes->has('_reverse_proxy'));
        self::assertEquals($attributes, $request->attributes->get('_reverse_proxy'));
    }

    public function testOnKernelControllerArgumentsIgnoresWhenNoReverseProxyAttribute(): void
    {
        $subscriber = new ReverseProxySubscriber(true);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        $event = new ControllerArgumentsEvent(
            $kernel,
            fn () => new Response(),
            [],
            $request,
            null
        );

        $subscriber->onKernelControllerArguments($event);

        self::assertFalse($request->attributes->has('_reverse_proxy'));
    }

    public function testOnKernelResponseDoesNothingWhenCacheDisabled(): void
    {
        $subscriber = new ReverseProxySubscriber(false);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response();

        $reverseProxy = new ReverseProxy(ttl: 3600);
        $request->attributes->set('_reverse_proxy', [$reverseProxy]);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        self::assertFalse($response->headers->has('X-Reverse-Proxy-TTL'));
    }

    public function testOnKernelResponseDoesNothingWhenMethodNotCacheable(): void
    {
        $subscriber = new ReverseProxySubscriber(true);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'POST']);
        $response = new Response();

        $reverseProxy = new ReverseProxy(ttl: 3600);
        $request->attributes->set('_reverse_proxy', [$reverseProxy]);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        self::assertFalse($response->headers->has('X-Reverse-Proxy-TTL'));
    }

    public function testOnKernelResponseDoesNothingWhenNoReverseProxyAttribute(): void
    {
        $subscriber = new ReverseProxySubscriber(true);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response();

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        self::assertFalse($response->headers->has('X-Reverse-Proxy-TTL'));
    }

    public function testOnKernelResponseSetsTtlFromTtlParameter(): void
    {
        $subscriber = new ReverseProxySubscriber(true);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response();

        $reverseProxy = new ReverseProxy(ttl: 3600);
        $request->attributes->set('_reverse_proxy', [$reverseProxy]);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        self::assertTrue($response->headers->has('X-Reverse-Proxy-TTL'));
        self::assertEquals('3600', $response->headers->get('X-Reverse-Proxy-TTL'));
    }

    public function testOnKernelResponseSetsTtlFromExpiresParameter(): void
    {
        $subscriber = new ReverseProxySubscriber(true);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response();

        $reverseProxy = new ReverseProxy(expires: '+1 hour');
        $request->attributes->set('_reverse_proxy', [$reverseProxy]);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        self::assertTrue($response->headers->has('X-Reverse-Proxy-TTL'));
        $ttl = (int) $response->headers->get('X-Reverse-Proxy-TTL');

        // Should be approximately 3600 seconds (1 hour), with some tolerance
        self::assertGreaterThanOrEqual(3595, $ttl);
        self::assertLessThanOrEqual(3600, $ttl);
    }

    public function testOnKernelResponseTtlParameterTakesPrecedenceOverExpires(): void
    {
        $subscriber = new ReverseProxySubscriber(true);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response();

        $reverseProxy = new ReverseProxy(ttl: 1800, expires: '+1 hour');
        $request->attributes->set('_reverse_proxy', [$reverseProxy]);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        self::assertTrue($response->headers->has('X-Reverse-Proxy-TTL'));
        self::assertEquals('1800', $response->headers->get('X-Reverse-Proxy-TTL'));
    }

    public function testOnKernelResponseDoesNothingWhenBothTtlAndExpiresAreNull(): void
    {
        $subscriber = new ReverseProxySubscriber(true);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response();

        $reverseProxy = new ReverseProxy();
        $request->attributes->set('_reverse_proxy', [$reverseProxy]);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        self::assertFalse($response->headers->has('X-Reverse-Proxy-TTL'));
    }

    public function testOnKernelResponseHandlesMultipleReverseProxyAttributes(): void
    {
        $subscriber = new ReverseProxySubscriber(true);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response();

        // When multiple attributes exist, the loop processes all but returns after the first one with both ttl and expires as null
        // If all have values, it processes all and the last one wins (overwrites the header)
        $reverseProxy1 = new ReverseProxy(ttl: 1800);
        $reverseProxy2 = new ReverseProxy(); // This has null ttl and expires, causing early return
        $request->attributes->set('_reverse_proxy', [$reverseProxy1, $reverseProxy2]);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        // The loop processes the first one (sets header), then hits the second one (null/null) and returns
        // Actually looking at the code, it processes each one in the loop but returns if ttl and expires are both null
        // So it should set 1800, then hit the return statement
        self::assertTrue($response->headers->has('X-Reverse-Proxy-TTL'));
        self::assertEquals('1800', $response->headers->get('X-Reverse-Proxy-TTL'));
    }

    public function testOnKernelResponseHandlesExpiredTime(): void
    {
        $subscriber = new ReverseProxySubscriber(true);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response();

        // Set expires to a time in the past
        $reverseProxy = new ReverseProxy(expires: '-1 hour');
        $request->attributes->set('_reverse_proxy', [$reverseProxy]);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        self::assertTrue($response->headers->has('X-Reverse-Proxy-TTL'));
        // Should be 0 (max of negative value and 0)
        self::assertEquals('0', $response->headers->get('X-Reverse-Proxy-TTL'));
    }

    public function testOnKernelResponseWithZeroTtl(): void
    {
        $subscriber = new ReverseProxySubscriber(true);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response();

        $reverseProxy = new ReverseProxy(ttl: 0);
        $request->attributes->set('_reverse_proxy', [$reverseProxy]);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        self::assertTrue($response->headers->has('X-Reverse-Proxy-TTL'));
        self::assertEquals('0', $response->headers->get('X-Reverse-Proxy-TTL'));
    }
}
