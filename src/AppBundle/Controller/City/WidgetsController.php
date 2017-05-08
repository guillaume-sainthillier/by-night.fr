<?php

namespace AppBundle\Controller\City;

use AppBundle\Entity\Site;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Controller\TBNController as Controller;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Description of MenuDroitController
 *
 * @author guillaume
 */
class WidgetsController extends Controller
{
    const FB_MEMBERS_LIMIT = 100;
    const TWEET_LIMIT = 25;
    const WIDGET_ITEM_LIMIT = 7;

    /**
     * @Route("/tweeter-feed/{max_id}", name="tbn_agenda_tweeter_feed", requirements={"max_id": "\d+"})
     */
    public function twitterAction(Site $site, $max_id = null)
    {
        $results = $this->get('tbn.social.twitter')->getTimeline($site, $max_id, self::TWEET_LIMIT);

        $nextLink = null;
        if (isset($results['search_metadata']['next_results'])) {
            parse_str($results['search_metadata']['next_results'], $infos);

            if (isset($infos['?max_id'])) {
                $nextLink = $this->generateUrl('tbn_agenda_tweeter_feed', [
                    'city' => $site->getSubdomain(),
                    'max_id' => $infos['?max_id']
                ]);
            }
        }

        if (!isset($results['statuses'])) {
            $results['statuses'] = [];
        }

        if (!count($results['statuses']) && $this->get('request_stack')->getParentRequest() === null) {
            return $this->redirectToRoute("tbn_agenda_agenda", ["city" => $site->getSubdomain()]);
        }

        $response = $this->render('City/Hinclude/tweets.html.twig', [
            'tweets' => $results['statuses'],
            'hasNextLink' => $nextLink
        ]);

        if (!$max_id || count($results['statuses']) !== self::TWEET_LIMIT) {
            list($expire, $ttl) = $this->getSecondsUntil(1);
        } else {
            $expire = new \DateTime;
            $expire->modify("+1 year");
            $ttl = 31536000;
        }

        $response->headers->add([
            'X-No-Browser-Cache' => '1'
        ]);

        return $response
            ->setSharedMaxAge($ttl)
            ->setExpires($expire);
    }

    /**
     * @Route("/soiree/{slug}--{id}.html/prochaines-soirees/{page}", name="tbn_agenda_prochaines_soirees", requirements={"slug": ".+", "id": "\d+", "page": "\d+"})
     */
    public function nextEventsAction(Site $site, $slug, $id = null, $page = 1)
    {
        if ($page <= 0) {
            $page = 1;
        }

        $result = $this->checkEventUrl($slug, $id, 'tbn_agenda_prochaines_soirees', [
            'page' => $page,
            'city' => $site->getSubdomain()
        ]);

        if ($result instanceof Response) {
            return $result;
        }
        $soiree = $result;

        if (!$soiree->getPlace()) {
            return $this->redirectToRoute('tbn_agenda_details', [
                'id' => $soiree->getId(),
                'slug' => $soiree->getSlug(),
                'city' => $site->getSubdomain()
            ]);
        }

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("AppBundle:Agenda");

        $count = $repo->findAllNextCount($soiree);
        $current = $page * self::WIDGET_ITEM_LIMIT;

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('tbn_agenda_prochaines_soirees', [
                'slug' => $soiree->getSlug(),
                'id' => $soiree->getId(),
                'city' => $soiree->getSite()->getSubdomain(),
                'page' => $page + 1
            ]);
        } else {
            $hasNextLink = null;
        }

        $response = $this->render("City/Hinclude/evenements_details.html.twig", [
            "page" => $page,
            "place" => $soiree->getPlace(),
            "soirees" => $repo->findAllNext($soiree, $page, self::WIDGET_ITEM_LIMIT),
            "current" => $current,
            "count" => $count,
            "hasNextLink" => $hasNextLink
        ]);

        $response->headers->add([
            'X-No-Browser-Cache' => '1'
        ]);

        return $response
            ->setExpires(new \DateTime('+1 year'))
            ->setSharedMaxAge(31536000)
            ->setPublic();
    }

    /**
     * @Route("/soiree/{slug}--{id}.html/autres-soirees/{page}", name="tbn_agenda_soirees_similaires", requirements={"slug": ".+", "id": "\d+", "page": "\d+"}))
     */
    public function soireesSimilairesAction(Site $site, $slug, $id = null, $page = 1)
    {
        if ($page <= 0) {
            $page = 1;
        }

        $result = $this->checkEventUrl($slug, $id, 'tbn_agenda_soirees_similaires', ['page' => $page, 'city' => $site->getSubdomain()]);
        if ($result instanceof Response) {
            return $result;
        }
        $soiree = $result;

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("AppBundle:Agenda");

        $count = $repo->findAllSimilairesCount($soiree);
        $current = $page * self::WIDGET_ITEM_LIMIT;

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('tbn_agenda_soirees_similaires', [
                'city' => $site->getSubdomain(),
                'slug' => $soiree->getSlug(),
                'id' => $soiree->getId(),
                'page' => $page + 1
            ]);
        } else {
            $hasNextLink = null;
        }

        $response = $this->render("City/Hinclude/evenements.html.twig", [
            'site' => $site,
            "soirees" => $repo->findAllSimilaires($soiree, $page, self::WIDGET_ITEM_LIMIT),
            "current" => $current,
            "count" => $count,
            "hasNextLink" => $hasNextLink
        ]);

        $response->headers->add([
            'X-No-Browser-Cache' => '1'
        ]);

        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic();
    }

    /**
     * @Route("/top/soirees/{page}", name="tbn_agenda_top_soirees", requirements={"page": "\d+"})
     */
    public function topSoireesAction(Site $site, $page = 1)
    {
        if ($page <= 1) {
            $page = 1;
        }

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('AppBundle:Agenda');

        $current = $page * self::WIDGET_ITEM_LIMIT;
        $count = $repo->findTopSoireeCount($site);

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('tbn_agenda_top_soirees', [
                'page' => $page + 1,
                'city' => $site->getSubdomain()
            ]);
        } else {
            $hasNextLink = null;
        }

        $response = $this->render("City/Hinclude/evenements.html.twig", [
            "site" => $site,
            "soirees" => $repo->findTopSoiree($site, $page, self::WIDGET_ITEM_LIMIT),
            "hasNextLink" => $hasNextLink,
            "current" => $current,
            "count" => $count
        ]);

        $response->headers->add([
            'X-No-Browser-Cache' => '1'
        ]);

        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic();
    }

    /**
     * @Route("/soiree/{slug}--{id}.html/membres/{page}", name="tbn_agenda_soirees_membres", requirements={"slug": ".+", "id": "\d+", "page": "\d+"}))
     */
    public function fbMembresAction(Site $site, $slug, $id = null, $page = 1)
    {
        if ($page <= 1) {
            $page = 1;
        }

        $result = $this->checkEventUrl($slug, $id, 'tbn_agenda_soirees_membres', ['page' => $page, 'city' => $site]);
        if ($result instanceof Response) {
            return $result;
        }
        $soiree = $result;

        if (!$soiree->getFacebookEventId()) {
            return $this->redirectToRoute('tbn_agenda_details', [
                'slug' => $soiree->getSlug(),
                'id' => $soiree->getId(),
                'city' => $soiree->getSite()->getSubdomain()
            ]);
        }

        $api = $this->get("tbn.social.facebook_admin");
        $retour = $api->getEventMembres($soiree->getFacebookEventId(), ($page - 1) * self::FB_MEMBERS_LIMIT, self::FB_MEMBERS_LIMIT);

        $membres = array_merge($retour['participations'], $retour['interets']);
        if (count($retour['interets']) == self::FB_MEMBERS_LIMIT || count($retour['participations']) == self::FB_MEMBERS_LIMIT) {
            $hasNextLink = $this->generateUrl('tbn_agenda_soirees_membres', [
                'city' => $soiree->getSite()->getSubdomain(),
                'slug' => $soiree->getSlug(),
                'id' => $soiree->getId(),
                'page' => $page + 1
            ]);
        } else {
            $hasNextLink = null;
        }

        $response = $this->render("City/Hinclude/fb_membres.html.twig", [
            "site" => $site,
            "event" => $soiree,
            "page" => $page,
            "membres" => $membres,
            "hasNextLink" => $hasNextLink
        ]);

        $now = new \DateTime();
        if ($soiree->getDateFin() < $now) {
            $now->modify("+1 year");
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

        $this->get('fos_http_cache.handler.tag_handler')->addTags(['fb-membres']);

        $response->headers->add([
            'X-No-Browser-Cache' => '1'
        ]);

        return $response->setPublic();
    }
}
