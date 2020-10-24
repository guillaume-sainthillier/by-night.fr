<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\City;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\TBNController as BaseController;
use App\Entity\Event;
use App\Event\EventCheckUrlEvent;
use App\Event\Events;
use App\Repository\EventRepository;
use App\Social\Twitter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class WidgetsController extends BaseController
{
    const TWEET_LIMIT = 25;
    const WIDGET_ITEM_LIMIT = 7;

    /**
     * @Route("/tweeter-feed/{max_id}", name="app_agenda_tweeter_feed", requirements={"max_id"="\d+"}, methods={"GET"})
     * @ReverseProxy(expires="1 hour")
     */
    public function twitter(bool $disableTwitterFeed, Location $location, Twitter $twitter, int $max_id = null): Response
    {
        $results = !$disableTwitterFeed ? $twitter->getTimeline($location, $max_id, self::TWEET_LIMIT) : [];

        $nextLink = null;
        if (isset($results['search_metadata']['next_results'])) {
            \parse_str($results['search_metadata']['next_results'], $infos);

            if (isset($infos['?max_id'])) {
                $nextLink = $this->generateUrl('app_agenda_tweeter_feed', [
                    'location' => $location->getSlug(),
                    'max_id' => $infos['?max_id'],
                ]);
            }
        }

        if (!isset($results['statuses'])) {
            $results['statuses'] = [];
        }

        return $this->render('City/Hinclude/tweets.html.twig', [
            'tweets' => $results['statuses'],
            'hasNextLink' => $nextLink,
            'location' => $location,
        ]);
    }

    /**
     * @Route("/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}/prochaines-soirees/{page<%patterns.page%>}", name="app_event_prochaines_soirees", methods={"GET"})
     * @ReverseProxy(expires="tomorrow")
     */
    public function nextEvents(Location $location, EventDispatcherInterface $eventDispatcher, EventRepository $eventRepository, string $slug, ?int $id = null, int $page = 1): Response
    {
        $eventCheck = new EventCheckUrlEvent($id, $slug, $location->getSlug(), 'app_event_prochaines_soirees', ['page' => $page]);
        $eventDispatcher->dispatch($eventCheck, Events::CHECK_EVENT_URL);
        if (null !== $eventCheck->getResponse()) {
            return $eventCheck->getResponse();
        }
        $event = $eventCheck->getEvent();

        $count = $eventRepository->findAllNextCount($event);
        $current = $page * self::WIDGET_ITEM_LIMIT;

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('app_event_prochaines_soirees', [
                'slug' => $event->getSlug(),
                'id' => $event->getId(),
                'location' => $location->getSlug(),
                'page' => $page + 1,
            ]);
        } else {
            $hasNextLink = null;
        }

        return $this->render('City/Hinclude/evenements_details.html.twig', [
            'page' => $page,
            'place' => $event->getPlace(),
            'events' => $eventRepository->findAllNext($event, $page, self::WIDGET_ITEM_LIMIT),
            'current' => $current,
            'count' => $count,
            'hasNextLink' => $hasNextLink,
        ]);
    }

    /**
     * @Route("/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}/autres-soirees/{page<%patterns.page%>}", name="app_event_soirees_similaires", methods={"GET"})
     * @ReverseProxy(expires="tomorrow")
     */
    public function soireesSimilaires(Location $location, EventDispatcherInterface $eventDispatcher, EventRepository $eventRepository, string $slug, ?int $id = null, ?int $page = 1): Response
    {
        $eventCheck = new EventCheckUrlEvent($id, $slug, $location->getSlug(), 'app_event_soirees_similaires', ['page' => $page]);
        $eventDispatcher->dispatch($eventCheck, Events::CHECK_EVENT_URL);
        if (null !== $eventCheck->getResponse()) {
            return $eventCheck->getResponse();
        }
        $event = $eventCheck->getEvent();
        $count = $eventRepository->findAllSimilairesCount($event);
        $current = $page * self::WIDGET_ITEM_LIMIT;

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('app_event_soirees_similaires', [
                'location' => $location->getSlug(),
                'slug' => $event->getSlug(),
                'id' => $event->getId(),
                'page' => $page + 1,
            ]);
        } else {
            $hasNextLink = null;
        }

        return $this->render('City/Hinclude/evenements_details.html.twig', [
            'page' => $page,
            'place' => $event->getPlace(),
            'events' => $eventRepository->findAllSimilaires($event, $page, self::WIDGET_ITEM_LIMIT),
            'current' => $current,
            'count' => $count,
            'hasNextLink' => $hasNextLink,
        ]);
    }

    /**
     * @Route("/top/soirees/{page<%patterns.page%>}", name="app_agenda_top_soirees", methods={"GET"})
     * @ReverseProxy(expires="tomorrow")
     */
    public function topSoirees(Location $location, EventRepository $eventRepository, int $page = 1): Response
    {
        $current = $page * self::WIDGET_ITEM_LIMIT;
        $count = $eventRepository->findTopSoireeCount($location);

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('app_agenda_top_soirees', [
                'page' => $page + 1,
                'location' => $location->getSlug(),
            ]);
        } else {
            $hasNextLink = null;
        }

        return $this->render('City/Hinclude/evenements.html.twig', [
            'location' => $location,
            'events' => $eventRepository->findTopSoiree($location, $page, self::WIDGET_ITEM_LIMIT),
            'hasNextLink' => $hasNextLink,
            'current' => $current,
            'count' => $count,
        ]);
    }
}
