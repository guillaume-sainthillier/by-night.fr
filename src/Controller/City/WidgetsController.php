<?php

namespace App\Controller\City;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\TBNController as BaseController;
use App\Entity\Event;
use App\Social\Twitter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Description of MenuDroitController.
 *
 * @author guillaume
 */
class WidgetsController extends BaseController
{
    const TWEET_LIMIT = 25;
    const WIDGET_ITEM_LIMIT = 7;

    /**
     * @Route("/tweeter-feed/{max_id}", name="app_agenda_tweeter_feed", requirements={"max_id": "\d+"})
     * @ReverseProxy(expires="1 hour")
     */
    public function twitterAction(bool $disableTwitterFeed, Location $location, Twitter $twitter, $max_id = null)
    {
        if(! $disableTwitterFeed) {
            $results = $twitter->getTimeline($location, $max_id, self::TWEET_LIMIT);
        } else {
            $results = [];
        }

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
     * @Route("/soiree/{slug}--{id}/prochaines-soirees/{page}", name="app_event_prochaines_soirees", requirements={"slug": "[^/]+", "id": "\d+", "page": "\d+"})
     * @ReverseProxy(expires="tomorrow")
     */
    public function nextEventsAction(Location $location, $slug, $id = null, $page = 1)
    {
        $result = $this->checkEventUrl($location->getSlug(), $slug, $id, 'app_event_prochaines_soirees', [
            'page' => $page,
        ]);

        if ($result instanceof Response) {
            return $result;
        }
        $event = $result;

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Event::class);

        $count = $repo->findAllNextCount($event);
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
            'events' => $repo->findAllNext($event, $page, self::WIDGET_ITEM_LIMIT),
            'current' => $current,
            'count' => $count,
            'hasNextLink' => $hasNextLink,
        ]);
    }

    /**
     * @Route("/soiree/{slug}--{id}/autres-soirees/{page}", name="app_event_soirees_similaires", requirements={"slug": "[^/]+", "id": "\d+", "page": "\d+"}))3
     * @ReverseProxy(expires="tomorrow")
     */
    public function soireesSimilairesAction(Location $location, $slug, $id = null, $page = 1)
    {
        $result = $this->checkEventUrl($location->getSlug(), $slug, $id, 'app_event_soirees_similaires', [
            'page' => $page,
        ]);

        if ($result instanceof Response) {
            return $result;
        }
        $event = $result;

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Event::class);

        $count = $repo->findAllSimilairesCount($event);
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

        return $this->render('City/Hinclude/evenements.html.twig', [
            'events' => $repo->findAllSimilaires($event, $page, self::WIDGET_ITEM_LIMIT),
            'current' => $current,
            'count' => $count,
            'hasNextLink' => $hasNextLink,
        ]);
    }

    /**
     * @Route("/top/soirees/{page}", name="app_agenda_top_soirees", requirements={"page": "\d+"})
     * @ReverseProxy(expires="tomorrow")
     */
    public function topSoireesAction(Location $location, $page = 1)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Event::class);

        $current = $page * self::WIDGET_ITEM_LIMIT;
        $count = $repo->findTopSoireeCount($location);

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
            'events' => $repo->findTopSoiree($location, $page, self::WIDGET_ITEM_LIMIT),
            'hasNextLink' => $hasNextLink,
            'current' => $current,
            'count' => $count,
        ]);
    }
}
