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
use App\App\CountrySlugs;
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
 * Populates AppContext with the location of the URL parameter; the pages without one take the member's city (AppContext).
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
        private CountrySlugs $countrySlugs,
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
        }
    }

    /**
     * Resolve location from URL slug using lazy loading.
     * The actual database query is deferred until entity properties are accessed.
     */
    private function resolveLocationFromUrl(Request $request): void
    {
        $locationSlug = $request->attributes->getString('location');

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
            // Create lazy-loaded location - database query deferred until access. Countries and cities share the
            // slugs of the URLs ("/france", "/toulouse"): a country's comes first
            if ($this->countrySlugs->has($locationSlug)) {
                $location = $this->lazyLocationFactory->createWithLazyCountry($locationSlug);
            } else {
                $location = $this->lazyLocationFactory->createWithLazyCity($locationSlug);
            }

            $this->appContext->setLocation($location);
        } catch (RuntimeException $e) {
            throw new NotFoundHttpException(\sprintf("La location '%s' est introuvable", $locationSlug), $e);
        }
    }
}
