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
use App\App\Location;
use App\Controller\AbstractController as BaseController;
use App\Dto\WidgetData\EventsWidgetData;
use App\Entity\Event;
use App\Manager\EventRedirectManager;
use App\Manager\WidgetsManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WidgetsController extends BaseController
{
    #[Route(path: '/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}/prochaines-soirees/{page<%patterns.page%>}', name: 'app_widget_next_events', methods: ['GET'])]
    public function nextEvents(AppContext $appContext, EventRedirectManager $eventRedirectManager, WidgetsManager $widgetsManager, string $slug, ?int $id = null, int $page = 1): Response
    {
        $location = $appContext->getLocation();
        $event = $eventRedirectManager->getVisibleEvent($id, $slug, $location->getSlug(), 'app_widget_next_events', ['page' => $page]);
        $eventsData = $widgetsManager->getNextEventsData($event, $location, $page);

        return $this->renderEventsPage('next', $event, $location, $eventsData);
    }

    #[Route(path: '/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}/autres-soirees/{page<%patterns.page%>}', name: 'app_widget_similar_events', methods: ['GET'])]
    public function similarEvents(AppContext $appContext, EventRedirectManager $eventRedirectManager, WidgetsManager $widgetsManager, string $slug, ?int $id = null, ?int $page = 1): Response
    {
        $location = $appContext->getLocation();
        $event = $eventRedirectManager->getVisibleEvent($id, $slug, $location->getSlug(), 'app_widget_similar_events', ['page' => $page]);
        $eventsData = $widgetsManager->getSimilarEventsData($event, $location, $page);

        return $this->renderEventsPage('similar', $event, $location, $eventsData);
    }

    /**
     * The cards alone for the "Plus" button of the event page (an AJAX request), a page of its own otherwise: the same
     * URL answers both, hence the Vary. Neither is indexed.
     */
    private function renderEventsPage(string $widget, Event $event, Location $location, ?EventsWidgetData $eventsData): Response
    {
        $response = $this->renderFragment('location/widget/events-page.html.twig', [
            'widget' => $widget,
            'event' => $event,
            'location' => $location,
            'eventsData' => $eventsData,
        ]);
        $response->setVary('X-Requested-With', false);

        return $response;
    }
}
