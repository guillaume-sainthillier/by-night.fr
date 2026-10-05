<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Exception\CityMovedException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

/**
 * A page of a city named by a former slug ("/geneve-1/agenda/sortir/concert") redirects to the same page under its
 * current one ("/suisse/geneve/agenda/sortir/concert"), with its query string, in one redirect: the former agenda goes
 * straight to the city's page, and a URL the router was about to strip of its trailing slash takes no extra step.
 */
final readonly class MovedCitySubscriber implements EventSubscriberInterface
{
    public function __construct(private RouterInterface $router)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $moved = self::find($event->getThrowable());
        $request = $event->getRequest();
        $route = $request->attributes->getString('_route');
        if (null === $moved || '' === $route) {
            return;
        }

        // The former agenda of a location is its page
        $route = 'app_agenda_legacy' === $route ? 'app_location_index' : $route;
        $event->setResponse(new RedirectResponse($this->url($request, $route, (string) $moved->getCity()->getSlug()), Response::HTTP_MOVED_PERMANENTLY));
    }

    /**
     * The exception, also when the template that first read the city wrapped it.
     */
    private static function find(?Throwable $throwable): ?CityMovedException
    {
        for (; null !== $throwable; $throwable = $throwable->getPrevious()) {
            if ($throwable instanceof CityMovedException) {
                return $throwable;
            }
        }

        return null;
    }

    private function url(Request $request, string $route, string $slug): string
    {
        // The parameters of the route's path, read from the request: the attributes of the router's own redirect (the
        // trailing slash) hold more
        $parameters = [];
        foreach ($this->router->getRouteCollection()->get($route)?->compile()->getPathVariables() ?? [] as $variable) {
            if ($request->attributes->has($variable)) {
                $parameters[$variable] = $request->attributes->get($variable);
            }
        }

        $url = $this->router->generate($route, ['location' => $slug] + $parameters);
        $query = $request->server->getString('QUERY_STRING');

        return '' !== $query ? $url . '?' . $query : $url;
    }
}
