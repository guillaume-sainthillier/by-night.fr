<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The front controller in the URL ("/index.php/paris/soiree/…", "/index.php"): Caddy's php_server runs public/index.php
 * for it, and Symfony takes "/index.php" for the base URL, so the page answered 200 and every URL the router wrote
 * on it, the canonical included, kept the prefix (Google indexed some). It leads to the same URL without it, query
 * string kept, before anything is rendered: no page is ever built on that base URL.
 */
final readonly class FrontControllerUrlSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Before LegacyCountryUrlSubscriber (64) and the router (32), which write URLs on the request's base URL
        return [KernelEvents::REQUEST => ['onKernelRequest', 128]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = strtok($request->getRequestUri(), '?');
        if (false === $path || 1 !== preg_match('#^/index\.php(?=/|$)(.*)$#i', $path, $matches)) {
            return;
        }

        $url = '/' . ltrim($matches[1], '/');
        $query = $request->server->getString('QUERY_STRING');
        if ('' !== $query) {
            $url .= '?' . $query;
        }

        // A form posted there is posted again, not turned into a GET
        $status = $request->isMethodSafe() ? Response::HTTP_MOVED_PERMANENTLY : Response::HTTP_PERMANENTLY_REDIRECT;
        $event->setResponse(new RedirectResponse($url, $status));
    }
}
