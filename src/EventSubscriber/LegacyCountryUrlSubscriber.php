<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Exception\RedirectException;
use App\Manager\EventRedirectManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * The countries' slugs carried a "c--" prefix until they shared the URLs with the cities' ("/c--france" is "/france"
 * now). The former URLs reach the new ones in a single redirect: this runs before the router, which would first send
 * "/c--france/" to "/c--france", and the former agenda "/c--france/agenda/2" to "/c--france/2". An event's page goes
 * straight to the URL the event answers to (EventRedirectManager): that of the page its duplicate redirects to, or its
 * city's since its place got one.
 */
final readonly class LegacyCountryUrlSubscriber implements EventSubscriberInterface
{
    /** The routes of an event's pages, which EventRedirectManager leads to the event's own URL */
    private const array EVENT_ROUTES = ['app_event_details', 'app_widget_next_events', 'app_widget_similar_events'];

    public function __construct(
        private UrlMatcherInterface $urlMatcher,
        private EventRedirectManager $eventRedirectManager,
    ) {
    }

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
        $url = $this->eventUrl('/' . $matches[1] . $path) ?? $request->getBaseUrl() . '/' . $matches[1] . $path;

        $query = $request->server->getString('QUERY_STRING');
        if ('' !== $query) {
            $url .= '?' . $query;
        }

        $event->setResponse(new RedirectResponse($url, Response::HTTP_MOVED_PERMANENTLY));
    }

    /**
     * The URL of the event the path names, when it is not that path. Null for any other page, an event that does not
     * exist, or a draft the visitor cannot see (EventRedirectManager tells nothing of it), which keep the path.
     */
    private function eventUrl(string $path): ?string
    {
        try {
            $parameters = $this->urlMatcher->match($path);
        } catch (ExceptionInterface) {
            return null;
        }

        if (!\in_array($parameters['_route'] ?? null, self::EVENT_ROUTES, true)) {
            return null;
        }

        $routeParams = array_filter($parameters, static fn (string $name): bool => !str_starts_with($name, '_') && !\in_array($name, ['id', 'slug', 'location'], true), \ARRAY_FILTER_USE_KEY);

        try {
            $this->eventRedirectManager->getEvent((int) $parameters['id'], (string) $parameters['slug'], (string) $parameters['location'], $parameters['_route'], $routeParams);
        } catch (RedirectException $redirect) {
            return $redirect->getUrl();
        } catch (NotFoundHttpException) {
        }

        return null;
    }
}
