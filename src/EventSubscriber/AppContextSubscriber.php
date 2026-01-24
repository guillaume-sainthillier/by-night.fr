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
use App\App\CityManager;
use App\App\Location;
use App\Entity\City;
use App\Entity\Country;
use App\Repository\CityRepository;
use App\Repository\CountryRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Populates AppContext with location from URL parameter or cookie fallback.
 * Runs early in the request lifecycle to ensure location context is always available.
 */
final readonly class AppContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AppContext $appContext,
        private CityManager $cityManager,
        private CityRepository $cityRepository,
        private CountryRepository $countryRepository,
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
            $this->resolveLocationFromCookie();
        }
    }

    /**
     * Resolve location from URL slug.
     */
    private function resolveLocationFromUrl(Request $request): void
    {
        $locationSlug = $request->attributes->get('location');

        // Handle special "unknown" location
        if ('unknown' === $locationSlug) {
            $noWhere = new Country();
            $noWhere->setName('Nowhere');
            $noWhere->setSlug($locationSlug);

            $location = new Location();
            $location->setCountry($noWhere);

            $this->appContext->setLocation($location);

            return;
        }

        // Resolve slug to City or Country entity
        if (!str_starts_with((string) $locationSlug, 'c--')) {
            $entity = $this->cityRepository->findOneBySlug($locationSlug);
        } else {
            $entity = $this->countryRepository->findOneBy(['slug' => $locationSlug]);
        }

        if (!$entity instanceof City && !$entity instanceof Country) {
            throw new NotFoundHttpException(\sprintf("La location '%s' est introuvable", $locationSlug));
        }

        $location = new Location();

        if ($entity instanceof City) {
            $location->setCity($entity);
        } else {
            $location->setCountry($entity);
        }

        $this->appContext->setLocation($location);
    }

    /**
     * Fallback to cookie city when no location in URL.
     */
    private function resolveLocationFromCookie(): void
    {
        $cookieCity = $this->cityManager->getCookieCity();

        if ($cookieCity) {
            $location = new Location();
            $location->setCity($cookieCity);
            $this->appContext->setLocation($location);
        }
        // Otherwise, AppContext remains empty (getLocation() returns null)
    }
}
