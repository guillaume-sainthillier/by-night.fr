<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Location;

use App\App\AppContext;
use App\Controller\AbstractController as BaseController;
use App\Event\EventCheckUrlEvent;
use App\Event\Events;
use App\Manager\WidgetsManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class WidgetsController extends BaseController
{
    #[Route(path: '/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}/prochaines-soirees/{page<%patterns.page%>}', name: 'app_widget_next_events', methods: ['GET'])]
    public function nextEvents(AppContext $appContext, EventDispatcherInterface $eventDispatcher, WidgetsManager $widgetsManager, string $slug, ?int $id = null, int $page = 1): Response
    {
        $location = $appContext->getLocation();

        $eventCheck = new EventCheckUrlEvent($id, $slug, $location->getSlug(), 'app_widget_next_events', ['page' => $page]);
        $eventDispatcher->dispatch($eventCheck, Events::CHECK_EVENT_URL);
        if (null !== $eventCheck->getResponse()) {
            return $eventCheck->getResponse();
        }

        $event = $eventCheck->getEvent();
        $eventsData = $widgetsManager->getNextEventsData($event, $location, $page);

        return $this->render('location/hinclude/details-events.html.twig', [
            'eventsData' => $eventsData,
        ]);
    }

    #[Route(path: '/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}/autres-soirees/{page<%patterns.page%>}', name: 'app_widget_similar_events', methods: ['GET'])]
    public function similarEvents(AppContext $appContext, EventDispatcherInterface $eventDispatcher, WidgetsManager $widgetsManager, string $slug, ?int $id = null, ?int $page = 1): Response
    {
        $location = $appContext->getLocation();

        $eventCheck = new EventCheckUrlEvent($id, $slug, $location->getSlug(), 'app_widget_similar_events', ['page' => $page]);
        $eventDispatcher->dispatch($eventCheck, Events::CHECK_EVENT_URL);
        if (null !== $eventCheck->getResponse()) {
            return $eventCheck->getResponse();
        }

        $event = $eventCheck->getEvent();
        $eventsData = $widgetsManager->getSimilarEventsData($event, $location, $page);

        return $this->render('location/hinclude/details-events.html.twig', [
            'eventsData' => $eventsData,
        ]);
    }

    #[Route(path: '/top/soirees/{page<%patterns.page%>}', name: 'app_widget_top_events', methods: ['GET'])]
    public function topEvents(AppContext $appContext, WidgetsManager $widgetsManager, int $page = 1): Response
    {
        $location = $appContext->getLocation();

        $topEventsData = $widgetsManager->getTopEventsData($location, $page);

        return $this->render('location/hinclude/events.html.twig', [
            'topEventsData' => $topEventsData,
        ]);
    }
}
