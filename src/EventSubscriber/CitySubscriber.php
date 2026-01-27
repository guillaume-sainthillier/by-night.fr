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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Updates the city cookie when a user visits a location-scoped page.
 * Uses AppContext to determine the current city from the URL.
 */
final readonly class CitySubscriber implements EventSubscriberInterface
{
    public function __construct(private AppContext $appContext)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Get the current city from AppContext (set by AppContextSubscriber)
        $city = $this->appContext->getCity();
        if (!$city) {
            return;
        }

        // Update cookie if it's different from the current city
        $request = $event->getRequest();
        $currentCookie = $request->cookies->get('app_city');

        if ($city->getSlug() !== $currentCookie) {
            $cookie = Cookie::create('app_city', $city->getSlug(), '+1 year');
            $event->getResponse()->headers->setCookie($cookie);
        }
    }
}
