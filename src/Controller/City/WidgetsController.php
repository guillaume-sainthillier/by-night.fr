<?php

namespace App\Controller\City;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\TBNController as BaseController;
use App\Entity\Agenda;
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
    const FB_MEMBERS_LIMIT = 100;

    const TWEET_LIMIT = 25;

    const WIDGET_ITEM_LIMIT = 7;

    /**
     * @Route("/tweeter-feed/{max_id}", name="app_agenda_tweeter_feed", requirements={"max_id": "\d+"})
     * @ReverseProxy(expires="1 hour")
     */
    public function twitterAction(Location $location, Twitter $twitter, RequestStack $requestStack, $max_id = null)
    {
        $results = $twitter->getTimeline($location, $max_id, self::TWEET_LIMIT);

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

        if (!\count($results['statuses']) && null === $requestStack->getParentRequest()) {
            return $this->redirectToRoute('app_agenda_agenda', ['location' => $location->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->render('City/Hinclude/tweets.html.twig', [
            'tweets' => $results['statuses'],
            'hasNextLink' => $nextLink,
            'location' => $location
        ]);
    }

    /**
     * @Route("/soiree/{slug}--{id}/prochaines-soirees/{page}", name="app_agenda_prochaines_soirees", requirements={"slug": "[^/]+", "id": "\d+", "page": "\d+"})
     * @ReverseProxy(expires="1 year")
     */
    public function nextEventsAction(Location $location, $slug, $id = null, $page = 1)
    {
        $result = $this->checkEventUrl($location->getSlug(), $slug, $id, 'app_agenda_prochaines_soirees', [
            'page' => $page,
        ]);

        if ($result instanceof Response) {
            return $result;
        }
        $soiree = $result;

        if (!$soiree->getPlace()) {
            return $this->redirectToRoute('app_agenda_details', [
                'id' => $soiree->getId(),
                'slug' => $soiree->getSlug(),
                'location' => $location->getSlug(),
            ], Response::HTTP_MOVED_PERMANENTLY);
        }

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Agenda::class);

        $count = $repo->findAllNextCount($soiree);
        $current = $page * self::WIDGET_ITEM_LIMIT;

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('app_agenda_prochaines_soirees', [
                'slug' => $soiree->getSlug(),
                'id' => $soiree->getId(),
                'location' => $soiree->getPlace()->getCity()->getSlug(),
                'page' => $page + 1,
            ]);
        } else {
            $hasNextLink = null;
        }

        return $this->render('City/Hinclude/evenements_details.html.twig', [
            'page' => $page,
            'place' => $soiree->getPlace(),
            'soirees' => $repo->findAllNext($soiree, $page, self::WIDGET_ITEM_LIMIT),
            'current' => $current,
            'count' => $count,
            'hasNextLink' => $hasNextLink,
        ]);
    }

    /**
     * @Route("/soiree/{slug}--{id}/autres-soirees/{page}", name="app_agenda_soirees_similaires", requirements={"slug": "[^/]+", "id": "\d+", "page": "\d+"}))3
     * @ReverseProxy(expires="tomorrow")
     */
    public function soireesSimilairesAction(Location $location, $slug, $id = null, $page = 1)
    {
        $result = $this->checkEventUrl($location->getSlug(), $slug, $id, 'app_agenda_soirees_similaires', [
            'page' => $page
        ]);

        if ($result instanceof Response) {
            return $result;
        }
        $soiree = $result;

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Agenda::class);

        $count = $repo->findAllSimilairesCount($soiree);
        $current = $page * self::WIDGET_ITEM_LIMIT;

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('app_agenda_soirees_similaires', [
                'location' => $location->getSlug(),
                'slug' => $soiree->getSlug(),
                'id' => $soiree->getId(),
                'page' => $page + 1,
            ]);
        } else {
            $hasNextLink = null;
        }

        return $this->render('City/Hinclude/evenements.html.twig', [
            'soirees' => $repo->findAllSimilaires($soiree, $page, self::WIDGET_ITEM_LIMIT),
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
        $repo = $em->getRepository(Agenda::class);

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
            'soirees' => $repo->findTopSoiree($location, $page, self::WIDGET_ITEM_LIMIT),
            'hasNextLink' => $hasNextLink,
            'current' => $current,
            'count' => $count,
        ]);
    }
}
