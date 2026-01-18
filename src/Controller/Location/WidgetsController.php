<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Location;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\AbstractController as BaseController;
use App\Event\EventCheckUrlEvent;
use App\Event\Events;
use App\Repository\EventRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class WidgetsController extends BaseController
{
    /**
     * @var int
     */
    public const WIDGET_ITEM_LIMIT = 7;

    #[Route(path: '/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}/prochaines-soirees/{page<%patterns.page%>}', name: 'app_widget_next_events', methods: ['GET'])]
    #[ReverseProxy(expires: 'tomorrow')]
    public function nextEvents(Location $location, EventDispatcherInterface $eventDispatcher, EventRepository $eventRepository, string $slug, ?int $id = null, int $page = 1): Response
    {
        $eventCheck = new EventCheckUrlEvent($id, $slug, $location->getSlug(), 'app_widget_next_events', ['page' => $page]);
        $eventDispatcher->dispatch($eventCheck, Events::CHECK_EVENT_URL);
        if (null !== $eventCheck->getResponse()) {
            return $eventCheck->getResponse();
        }

        $event = $eventCheck->getEvent();
        $count = $eventRepository->getAllNextCount($event);
        $current = $page * self::WIDGET_ITEM_LIMIT;
        if ($current < $count) {
            $hasNextLink = $this->generateUrl('app_widget_next_events', [
                'slug' => $event->getSlug(),
                'id' => $event->getId(),
                'location' => $location->getSlug(),
                'page' => $page + 1,
            ]);
        } else {
            $hasNextLink = null;
        }

        return $this->render('location/hinclude/details-events.html.twig', [
            'page' => $page,
            'place' => $event->getPlace(),
            'events' => $eventRepository->findAllNext($event, $page, self::WIDGET_ITEM_LIMIT),
            'current' => $current,
            'count' => $count,
            'hasNextLink' => $hasNextLink,
        ]);
    }

    #[Route(path: '/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}/autres-soirees/{page<%patterns.page%>}', name: 'app_widget_similar_events', methods: ['GET'])]
    #[ReverseProxy(expires: 'tomorrow')]
    public function similarEvents(Location $location, EventDispatcherInterface $eventDispatcher, EventRepository $eventRepository, string $slug, ?int $id = null, ?int $page = 1): Response
    {
        $eventCheck = new EventCheckUrlEvent($id, $slug, $location->getSlug(), 'app_widget_similar_events', ['page' => $page]);
        $eventDispatcher->dispatch($eventCheck, Events::CHECK_EVENT_URL);
        if (null !== $eventCheck->getResponse()) {
            return $eventCheck->getResponse();
        }

        $event = $eventCheck->getEvent();
        $count = $eventRepository->getAllSimilarsCount($event);
        $current = $page * self::WIDGET_ITEM_LIMIT;
        if ($current < $count) {
            $hasNextLink = $this->generateUrl('app_widget_similar_events', [
                'location' => $location->getSlug(),
                'slug' => $event->getSlug(),
                'id' => $event->getId(),
                'page' => $page + 1,
            ]);
        } else {
            $hasNextLink = null;
        }

        return $this->render('location/hinclude/details-events.html.twig', [
            'page' => $page,
            'place' => $event->getPlace(),
            'events' => $eventRepository->findAllSimilars($event, $page, self::WIDGET_ITEM_LIMIT),
            'current' => $current,
            'count' => $count,
            'hasNextLink' => $hasNextLink,
        ]);
    }

    #[Route(path: '/top/soirees/{page<%patterns.page%>}', name: 'app_widget_top_events', methods: ['GET'])]
    #[ReverseProxy(expires: 'tomorrow')]
    public function topEvents(Location $location, EventRepository $eventRepository, int $page = 1): Response
    {
        $current = $page * self::WIDGET_ITEM_LIMIT;
        $count = $eventRepository->getTopEventCount($location);
        if ($current < $count) {
            $hasNextLink = $this->generateUrl('app_widget_top_events', [
                'page' => $page + 1,
                'location' => $location->getSlug(),
            ]);
        } else {
            $hasNextLink = null;
        }

        return $this->render('location/hinclude/events.html.twig', [
            'location' => $location,
            'events' => $eventRepository->findTopEvents($location, $page, self::WIDGET_ITEM_LIMIT),
            'hasNextLink' => $hasNextLink,
            'current' => $current,
            'count' => $count,
        ]);
    }
}
