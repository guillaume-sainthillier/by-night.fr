<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Event\EventCheckUrlEvent;
use App\Event\Events;
use App\Repository\EventRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EventUrlCheckSubscriber implements EventSubscriberInterface
{
    private EventRepository $eventRepository;
    private RequestStack $requestStack;
    private UrlGeneratorInterface $router;

    public function __construct(RequestStack $requestStack, UrlGeneratorInterface $router, EventRepository $eventRepository)
    {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->eventRepository = $eventRepository;
    }

    public function onEventCheck(EventCheckUrlEvent $e): void
    {
        //Old route handle
        if (null === $e->getEventId()) {
            $event = $this->eventRepository->findOneBy(['slug' => $e->getEventSlug()]);
        } else {
            $event = $this->eventRepository->find($e->getEventId());
        }

        if (null === $event) {
            throw new NotFoundHttpException(null === $e->getEventId() ? sprintf('Event with slug "%s" not found', $e->getEventSlug()) : sprintf('Event with id "%d" not found', $e->getEventId()));
        }

        if (null === $this->requestStack->getParentRequest() && (
                null === $e->getEventId()
                || $event->getSlug() !== $e->getEventSlug()
                || $event->getLocationSlug() !== $e->getLocationSlug()
            )) {
            $routeParams = array_merge([
                'id' => $event->getId(),
                'slug' => $event->getSlug(),
                'location' => $event->getLocationSlug(),
            ], $e->getRouteParams());

            $response = new RedirectResponse(
                $this->router->generate($e->getRouteName(), $routeParams),
                Response::HTTP_MOVED_PERMANENTLY
            );
            $e->setResponse($response);
            $e->stopPropagation();

            return;
        }

        //All is ok :-)
        $e->setEvent($event);
    }

    public static function getSubscribedEvents()
    {
        return [
            Events::CHECK_EVENT_URL => 'onEventCheck',
        ];
    }
}
