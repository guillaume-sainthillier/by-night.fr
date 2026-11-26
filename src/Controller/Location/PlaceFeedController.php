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
use App\Feed\EventFeedManager;
use App\Manager\PlaceRedirectManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A venue's events outside its agenda: the calendar a visitor's calendar app subscribes to, and the widget the venue's
 * website embeds. The same for everyone, so they never open a session.
 */
final class PlaceFeedController extends BaseController
{
    #[Route(path: '/agenda/sortir-a/{placeSlug<%patterns.slug%>}/calendrier.ics', name: 'app_place_calendar', methods: ['GET'], stateless: true)]
    public function calendar(string $placeSlug, AppContext $appContext, PlaceRedirectManager $placeRedirectManager, EventFeedManager $eventFeedManager): Response
    {
        return $eventFeedManager->createCalendarResponse($placeRedirectManager->getPlace($placeSlug, $appContext->getLocation(), 'app_place_calendar'));
    }

    #[Route(path: '/agenda/sortir-a/{placeSlug<%patterns.slug%>}/widget', name: 'app_place_widget', methods: ['GET'], stateless: true)]
    public function widget(Request $request, string $placeSlug, AppContext $appContext, PlaceRedirectManager $placeRedirectManager, EventFeedManager $eventFeedManager): Response
    {
        return $eventFeedManager->createWidgetResponse(
            $placeRedirectManager->getPlace($placeSlug, $appContext->getLocation(), 'app_place_widget'),
            $request->query->getInt('limit', EventFeedManager::WIDGET_EVENTS),
            'dark' === $request->query->getString('theme'),
        );
    }
}
