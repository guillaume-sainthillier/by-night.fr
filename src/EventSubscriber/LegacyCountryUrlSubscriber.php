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
 * The countries' slugs carried a "c--" prefix until they shared the URLs with the cities' ("/c--france" is "/france"
 * now). The former URLs reach the new ones in a single redirect: this runs before the router, which would first send
 * "/c--france/" to "/c--france", and the former agenda "/c--france/agenda/2" to "/c--france/2".
 */
final class LegacyCountryUrlSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // The router listens at 32
        return [KernelEvents::REQUEST => ['onKernelRequest', 64]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethodSafe()
            || 1 !== preg_match('#^/c--([^/]+)(/.*)?$#', $request->getPathInfo(), $matches)) {
            return;
        }

        // Without its trailing slash, and the former agenda of the country is its page ("/agenda/2" is "/2")
        $path = preg_replace('#^/agenda(?=(?:/\d+)?$)#', '', rtrim($matches[2] ?? '', '/'));
        $url = $request->getBaseUrl() . '/' . $matches[1] . $path;

        $query = $request->server->getString('QUERY_STRING');
        if ('' !== $query) {
            $url .= '?' . $query;
        }

        $event->setResponse(new RedirectResponse($url, Response::HTTP_MOVED_PERMANENTLY));
    }
}
