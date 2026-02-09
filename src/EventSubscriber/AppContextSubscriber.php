<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\App\AppContext;
use App\App\LazyLocationFactory;
use App\App\Location;
use App\Entity\Country;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Populates AppContext with location from URL parameter or cookie fallback.
 * Runs early in the request lifecycle to ensure location context is always available.
 *
 * Uses PHP 8.4 LazyObject to defer database loading of City/Country entities
 * until their properties are actually accessed.
 */
final readonly class AppContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AppContext $appContext,
        private LazyLocationFactory $lazyLocationFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(KernelEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Check if route has a {location} parameter
        if ($request->attributes->has('location')) {
            $this->resolveLocationFromUrl($request);
        } else {
            // Non-location route: try cookie fallback
            $this->resolveLocationFromCookie($request);
        }
    }

    /**
     * Resolve location from URL slug using lazy loading.
     * The actual database query is deferred until entity properties are accessed.
     */
    private function resolveLocationFromUrl(Request $request): void
    {
        $locationSlug = $request->attributes->get('location');

        // Handle special "unknown" location (no lazy loading needed)
        if ('unknown' === $locationSlug) {
            $noWhere = new Country();
            $noWhere->setName('Nowhere');
            $noWhere->setSlug($locationSlug);

            $location = new Location();
            $location->setCountry($noWhere);

            $this->appContext->setLocation($location);

            return;
        }

        try {
            // Create lazy-loaded location - database query deferred until access
            if (!str_starts_with((string) $locationSlug, 'c--')) {
                $location = $this->lazyLocationFactory->createWithLazyCity($locationSlug);
            } else {
                $location = $this->lazyLocationFactory->createWithLazyCountry($locationSlug);
            }

            $this->appContext->setLocation($location);
        } catch (RuntimeException $e) {
            throw new NotFoundHttpException(\sprintf("La location '%s' est introuvable", $locationSlug), $e);
        }
    }

    /**
     * Fallback to cookie city when no location in URL.
     * Uses lazy loading to defer database query.
     */
    private function resolveLocationFromCookie(Request $request): void
    {
        if (!$request->cookies->has('app_city')) {
            return;
        }

        $citySlug = $request->cookies->get('app_city');
        if (empty($citySlug)) {
            return;
        }

        try {
            $location = $this->lazyLocationFactory->createWithLazyCity($citySlug);
            $this->appContext->setLocation($location);
        } catch (RuntimeException) {
            // Invalid cookie city, ignore silently
        }
    }
}
