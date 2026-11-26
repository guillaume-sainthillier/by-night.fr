<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Publisher;

use App\Controller\AbstractController as BaseController;
use App\Feed\EventFeedManager;
use App\Manager\PublisherRedirectManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A publisher's events outside its page: the calendar a visitor's calendar app subscribes to, and the widget its
 * website embeds (its page organisateur gives both addresses). The same for everyone, so they never open a session.
 */
#[Route(path: '/organisateurs')]
final class PublisherFeedController extends BaseController
{
    #[Route(path: '/{slug<%patterns.slug%>}/calendrier.ics', name: 'app_publisher_calendar', methods: ['GET'], stateless: true)]
    public function calendar(string $slug, PublisherRedirectManager $publisherRedirectManager, EventFeedManager $eventFeedManager): Response
    {
        return $eventFeedManager->createCalendarResponse($publisherRedirectManager->getPublisher($slug, 'app_publisher_calendar'));
    }

    #[Route(path: '/{slug<%patterns.slug%>}/widget', name: 'app_publisher_widget', methods: ['GET'], stateless: true)]
    public function widget(Request $request, string $slug, PublisherRedirectManager $publisherRedirectManager, EventFeedManager $eventFeedManager): Response
    {
        return $eventFeedManager->createWidgetResponse(
            $publisherRedirectManager->getPublisher($slug, 'app_publisher_widget'),
            $request->query->getInt('limit', EventFeedManager::WIDGET_EVENTS),
            'dark' === $request->query->getString('theme'),
        );
    }
}
