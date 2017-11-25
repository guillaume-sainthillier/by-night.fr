<?php

namespace AppBundle\Controller\City;

use AppBundle\Entity\City;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Controller\TBNController as Controller;
use Symfony\Component\Routing\Annotation\Route;
use AppBundle\Configuration\BrowserCache;

/**
 * Description of MenuDroitController.
 *
 * @author guillaume
 */
class WidgetsController extends Controller
{
    const FB_MEMBERS_LIMIT  = 100;
    const TWEET_LIMIT       = 25;
    const WIDGET_ITEM_LIMIT = 7;

    /**
     * @Route("/tweeter-feed/{max_id}", name="tbn_agenda_tweeter_feed", requirements={"max_id": "\d+"})
     * @BrowserCache(false)
     */
    public function twitterAction(City $city, $max_id = null)
    {
        $results = $this->get('tbn.social.twitter')->getTimeline($city, $max_id, self::TWEET_LIMIT);

        $nextLink = null;
        if (isset($results['search_metadata']['next_results'])) {
            \parse_str($results['search_metadata']['next_results'], $infos);

            if (isset($infos['?max_id'])) {
                $nextLink = $this->generateUrl('tbn_agenda_tweeter_feed', [
                    'city'   => $city->getSlug(),
                    'max_id' => $infos['?max_id'],
                ]);
            }
        }

        if (!isset($results['statuses'])) {
            $results['statuses'] = [];
        }

        if (!\count($results['statuses']) && null === $this->get('request_stack')->getParentRequest()) {
            return $this->redirectToRoute('tbn_agenda_agenda', ['city' => $city->getSlug()]);
        }

        $response = $this->render('City/Hinclude/tweets.html.twig', [
            'tweets'      => $results['statuses'],
            'hasNextLink' => $nextLink,
        ]);

        if (!$max_id || self::TWEET_LIMIT !== \count($results['statuses'])) {
            list($expire, $ttl) = $this->getSecondsUntil(1);
        } else {
            $expire = new \DateTime();
            $expire->modify('+1 year');
            $ttl = 31536000;
        }

        return $response
            ->setSharedMaxAge($ttl)
            ->setExpires($expire);
    }

    /**
     * @Route("/soiree/{slug}--{id}.html/prochaines-soirees/{page}", name="tbn_agenda_prochaines_soirees", requirements={"slug": ".+", "id": "\d+", "page": "\d+"})
     * @BrowserCache(false)
     */
    public function nextEventsAction(City $city, $slug, $id = null, $page = 1)
    {
        if ($page <= 0) {
            $page = 1;
        }

        $result = $this->checkEventUrl($city, $slug, $id, 'tbn_agenda_prochaines_soirees', [
            'page' => $page,
            'city' => $city->getSlug(),
        ]);

        if ($result instanceof Response) {
            return $result;
        }
        $soiree = $result;

        if (!$soiree->getPlace()) {
            return $this->redirectToRoute('tbn_agenda_details', [
                'id'   => $soiree->getId(),
                'slug' => $soiree->getSlug(),
                'city' => $city->getSlug(),
            ]);
        }

        $em   = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('AppBundle:Agenda');

        $count   = $repo->findAllNextCount($soiree);
        $current = $page * self::WIDGET_ITEM_LIMIT;

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('tbn_agenda_prochaines_soirees', [
                'slug' => $soiree->getSlug(),
                'id'   => $soiree->getId(),
                'city' => $soiree->getPlace()->getCity()->getSlug(),
                'page' => $page + 1,
            ]);
        } else {
            $hasNextLink = null;
        }

        $response = $this->render('City/Hinclude/evenements_details.html.twig', [
            'page'        => $page,
            'place'       => $soiree->getPlace(),
            'soirees'     => $repo->findAllNext($soiree, $page, self::WIDGET_ITEM_LIMIT),
            'current'     => $current,
            'count'       => $count,
            'hasNextLink' => $hasNextLink,
        ]);

        return $response
            ->setExpires(new \DateTime('+1 year'))
            ->setSharedMaxAge(31536000)
            ->setPublic();
    }

    /**
     * @Route("/soiree/{slug}--{id}.html/autres-soirees/{page}", name="tbn_agenda_soirees_similaires", requirements={"slug": ".+", "id": "\d+", "page": "\d+"}))3
     * @BrowserCache(false)
     */
    public function soireesSimilairesAction(City $city, $slug, $id = null, $page = 1)
    {
        if ($page <= 0) {
            $page = 1;
        }

        $result = $this->checkEventUrl($city, $slug, $id, 'tbn_agenda_soirees_similaires', ['page' => $page, 'city' => $city->getSlug()]);
        if ($result instanceof Response) {
            return $result;
        }
        $soiree = $result;

        $em   = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('AppBundle:Agenda');

        $count   = $repo->findAllSimilairesCount($soiree);
        $current = $page * self::WIDGET_ITEM_LIMIT;

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('tbn_agenda_soirees_similaires', [
                'city' => $city->getSlug(),
                'slug' => $soiree->getSlug(),
                'id'   => $soiree->getId(),
                'page' => $page + 1,
            ]);
        } else {
            $hasNextLink = null;
        }

        $response = $this->render('City/Hinclude/evenements.html.twig', [
            'site'        => $city,
            'soirees'     => $repo->findAllSimilaires($soiree, $page, self::WIDGET_ITEM_LIMIT),
            'current'     => $current,
            'count'       => $count,
            'hasNextLink' => $hasNextLink,
        ]);

        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic();
    }

    /**
     * @Route("/top/soirees/{page}", name="tbn_agenda_top_soirees", requirements={"page": "\d+"})
     * @BrowserCache(false)
     */
    public function topSoireesAction(City $city, $page = 1)
    {
        if ($page <= 1) {
            $page = 1;
        }

        $em   = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('AppBundle:Agenda');

        $current = $page * self::WIDGET_ITEM_LIMIT;
        $count   = $repo->findTopSoireeCount($city);

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('tbn_agenda_top_soirees', [
                'page' => $page + 1,
                'city' => $city->getSlug(),
            ]);
        } else {
            $hasNextLink = null;
        }

        $response = $this->render('City/Hinclude/evenements.html.twig', [
            'city'        => $city,
            'soirees'     => $repo->findTopSoiree($city, $page, self::WIDGET_ITEM_LIMIT),
            'hasNextLink' => $hasNextLink,
            'current'     => $current,
            'count'       => $count,
        ]);

        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic();
    }

    /**
     * @Route("/soiree/{slug}--{id}.html/membres/{page}", name="tbn_agenda_soirees_membres", requirements={"slug": ".+", "id": "\d+", "page": "\d+"}))
     * @BrowserCache(false)
     */
    public function fbMembresAction(City $city, $slug, $id = null, $page = 1)
    {
        if ($page <= 1) {
            $page = 1;
        }

        $result = $this->checkEventUrl($city, $slug, $id, 'tbn_agenda_soirees_membres', ['page' => $page, 'city' => $city->getSlug()]);
        if ($result instanceof Response) {
            return $result;
        }
        $soiree = $result;

        if (!$soiree->getFacebookEventId()) {
            return $this->redirectToRoute('tbn_agenda_details', [
                'slug' => $soiree->getSlug(),
                'id'   => $soiree->getId(),
                'city' => $soiree->getPlace()->getCity()->getSlug(),
            ]);
        }

        $api    = $this->get('tbn.social.facebook_admin');
        $retour = $api->getEventMembres($soiree->getFacebookEventId(), ($page - 1) * self::FB_MEMBERS_LIMIT, self::FB_MEMBERS_LIMIT);

        $membres = \array_merge($retour['participations'], $retour['interets']);
        if (self::FB_MEMBERS_LIMIT == \count($retour['interets']) || self::FB_MEMBERS_LIMIT == \count($retour['participations'])) {
            $hasNextLink = $this->generateUrl('tbn_agenda_soirees_membres', [
                'city' => $soiree->getPlace()->getCity()->getSlug(),
                'slug' => $soiree->getSlug(),
                'id'   => $soiree->getId(),
                'page' => $page + 1,
            ]);
        } else {
            $hasNextLink = null;
        }

        $response = $this->render('City/Hinclude/fb_membres.html.twig', [
            'city'        => $city,
            'event'       => $soiree,
            'page'        => $page,
            'membres'     => $membres,
            'hasNextLink' => $hasNextLink,
        ]);

        $now = new \DateTime();
        if ($soiree->getDateFin() < $now) {
            $now->modify('+1 year');
            $response
                ->setExpires($now)
                ->setSharedMaxAge(31536000);
        } else {
            if ($hasNextLink) {
                list($expires, $next2hours) = $this->getSecondsUntil(24);
            } else {
                list($expires, $next2hours) = $this->getSecondsUntil(2);
            }

            $response
                ->setExpires($expires)
                ->setSharedMaxAge($next2hours);
        }

        $this->get('fos_http_cache.http.symfony_response_tagger')->addTags(['fb-membres']);

        return $response->setPublic();
    }
}
