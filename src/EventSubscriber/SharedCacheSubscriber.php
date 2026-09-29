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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps a visitor's page shareable by the CDN when its controller asked for it (#[Cache(public: true)]).
 *
 * On a stateful firewall, merely reading the security token (the header's user menu, the member's city, a
 * voter) counts as using the session, even when there is none: the session listener then turns every page
 * private. A visitor without a session nor a remember-me cookie cannot be anyone, so the page they get is the
 * same for all of them: this subscriber tells the session listener to leave its Cache-Control alone.
 *
 * Members always carry one of those cookies, so the Cloudflare cache rule must not serve them from the cache
 * (it bypasses requests sending either cookie).
 */
final readonly class SharedCacheSubscriber implements EventSubscriberInterface
{
    /** The remember-me authenticator's default cookie (security.yaml sets no other name). */
    private const string REMEMBER_ME_COOKIE = 'REMEMBERME';

    public static function getSubscribedEvents(): array
    {
        return [
            // After #[Cache] fills the headers (-10), before the session listener overrides them (-1000)
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->isShareable($event->getRequest(), $event->getResponse())) {
            return;
        }

        $event->getResponse()->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
    }

    private function isShareable(Request $request, Response $response): bool
    {
        if (!$request->isMethodCacheable() || !$response->headers->hasCacheControlDirective('public')) {
            return false;
        }

        // A member, or someone about to be logged back in
        if ($request->hasPreviousSession() || $request->cookies->has(self::REMEMBER_ME_COOKIE)) {
            return false;
        }

        // Something to keep for this visitor (a flash message, a login target path...) sends them a session cookie
        if ([] !== $response->headers->getCookies()) {
            return false;
        }

        $session = $request->hasSession() ? $request->getSession() : null;

        return !$session instanceof Session || !$session->isStarted() || $session->isEmpty();
    }
}
