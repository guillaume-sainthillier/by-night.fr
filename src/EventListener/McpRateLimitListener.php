<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Throttles the MCP server per IP address: a public endpoint that searches Elasticsearch on each call. Refused, a
 * client is told when to come back (429, Retry-After).
 */
final readonly class McpRateLimitListener
{
    private const string ROUTE = '_mcp_endpoint_default';

    public function __construct(
        #[Autowire(service: 'limiter.mcp')]
        private RateLimiterFactoryInterface $limiterFactory,
    ) {
    }

    // After the router, which names the route
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || self::ROUTE !== $request->attributes->get('_route') || !$request->isMethod('POST')) {
            return;
        }

        $limit = $this->limiterFactory->create($request->getClientIp() ?? 'unknown')->consume();
        if ($limit->isAccepted()) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32000, 'message' => 'Too many requests, try again later.']],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => max(1, $limit->getRetryAfter()->getTimestamp() - time())],
        ));
    }
}
