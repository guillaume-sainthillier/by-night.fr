<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class TBNController extends AbstractController
{
    protected RequestStack $requestStack;

    protected EventRepository $eventRepository;

    public function __construct(RequestStack $requestStack, EventRepository $eventRepository)
    {
        $this->requestStack = $requestStack;
        $this->eventRepository = $eventRepository;
    }

    protected function checkEventUrl($locationSlug, $eventSlug, $eventId, $routeName = 'app_event_details', array $extraParams = [])
    {
        $repoEvent = $this->eventRepository;
        $event = !$eventId ? $this->repository->findOneBy(['slug' => $eventSlug]) : $this->repository->find($eventId);

        if (!$event || !$event->getSlug()) {
            throw $this->createNotFoundException('Event not found');
        }

        if (null === $this->requestStack->getParentRequest() && (
                !$eventId
                || $event->getSlug() !== $eventSlug
                || $event->getLocationSlug() !== $locationSlug
            )) {
            $routeParams = \array_merge([
                'id' => $event->getId(),
                'slug' => $event->getSlug(),
                'location' => $event->getLocationSlug(),
            ], $extraParams);

            return $this->redirectToRoute($routeName, $routeParams, Response::HTTP_MOVED_PERMANENTLY);
        }

        return $event;
    }
}
