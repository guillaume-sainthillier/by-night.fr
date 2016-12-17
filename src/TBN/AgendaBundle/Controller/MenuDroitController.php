<?php

namespace TBN\AgendaBundle\Controller;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Controller\TBNController as Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;

/**
 * Description of MenuDroitController
 *
 * @author guillaume
 */
class MenuDroitController extends Controller
{
    const FB_MEMBERS_LIMIT = 50;
    const WIDGET_ITEM_LIMIT = 7;

    public function programmeTVAction()
    {
        $parser = $this->get("tbn.programmetv");
        $programmes = $parser->getProgrammesTV();

        $response = $this->render("TBNAgendaBundle:Hinclude:programme_tv.html.twig", [
            "programmes" => $programmes
        ]);

        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic()
        ;
    }

    public function soireesSimilairesAction(Agenda $soiree, $page = 1)
    {
        if ($page <= 0) {
            $page = 1;
        }
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");

        $count = $repo->findAllSimilairesCount($soiree);
        $current = $page * self::WIDGET_ITEM_LIMIT;

        if($current < $count) {
            $hasNextLink = $this->generateUrl('tbn_agenda_soirees_similaires', [
                'slug' => $soiree->getSlug(),
                'page' => $page + 1
            ]);
        }else {
            $hasNextLink = null;
        }

        $response = $this->render("TBNAgendaBundle:Hinclude:evenements.html.twig", [
            "soirees" => $repo->findAllSimilaires($soiree, $page, self::WIDGET_ITEM_LIMIT),
            "current" => $current,
            "count" => $count,
            "hasNextLink" => $hasNextLink
        ]);

        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic()
        ;
    }

    public function topSoireesAction($page = 1)
    {
        if ($page <= 1) {
            $page = 1;
        }

        $siteManager = $this->container->get("site_manager");
        $site = $siteManager->getCurrentSite();

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('TBNAgendaBundle:Agenda');

        $current = $page * self::WIDGET_ITEM_LIMIT;
        $count = $repo->findTopSoireeCount($site);

        if($current < $count) {
            $hasNextLink = $this->generateUrl('tbn_agenda_top_soirees', [
                'page' => $page + 1
            ]);
        }else {
            $hasNextLink = null;
        }

        $response = $this->render("TBNAgendaBundle:Hinclude:evenements.html.twig", [
            "soirees" => $repo->findTopSoiree($site, $page, self::WIDGET_ITEM_LIMIT),
            "hasNextLink" => $hasNextLink,
            "current" => $current,
            "count" => $count
        ]);

        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic()
        ;
    }

    /**
     * TODO: Delete this action
     */
    public function tendancesAction(Agenda $soiree)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        $nbItems = 30;
        $membres = $this->getFBMembres($soiree, 1, $nbItems);

        return $this->render("TBNAgendaBundle:Hinclude:tendances.html.twig", [
            "tendancesParticipations" => $repo->findAllTendancesParticipations($soiree),
            "tendancesInterets" => $repo->findAllTendancesInterets($soiree),
            "count_participer" => $soiree->getParticipations() + $soiree->getFbParticipations(),
            "count_interets" => $soiree->getInterets() + $soiree->getFbInterets(),
            "membres" => $membres,
            "maxItems" => $nbItems
        ]);
    }

    public function fbMembresAction(Agenda $soiree, $page)
    {
        if(! $soiree->getFacebookEventId()) {
            return $this->redirectToRoute('tbn_agenda_details', ['slug' => $soiree->getSlug()]);
        }

        if ($page <= 1) {
            $page = 1;
        }

        $api = $this->get("tbn.social.facebook_admin");
        $retour = $api->getEventMembres($soiree->getFacebookEventId(), ($page - 1) * self::FB_MEMBERS_LIMIT, self::FB_MEMBERS_LIMIT);

        $membres = array_merge($retour['participations'], $retour['interets']);
        if(count($retour['interets']) == self::FB_MEMBERS_LIMIT || count($retour['participations']) == self::FB_MEMBERS_LIMIT) {
            $hasNextLink = $this->generateUrl('tbn_agenda_soirees_membres', [
                'slug' => $soiree->getSlug(),
                'page' => $page + 1
            ]);
        }else {
            $hasNextLink = null;
        }

        $response = $this->render("TBNAgendaBundle:Hinclude:fb_membres.html.twig", [
            "event" => $soiree,
            "page" => $page,
            "membres" => $membres,
            "hasNextLink" => $hasNextLink
        ]);

        try {
            $now = new \DateTime();
            if ($soiree->getDateFin() < $now) {
                $now->modify("+1 year");
                $response
                    ->setExpires($now)
                    ->setSharedMaxAge(31536000);
            } else {
                list($expires, $next2hours) = $this->getSecondsUntil(2);
                $response
                    ->setExpires($expires)
                    ->setSharedMaxAge($next2hours);
            }
        }catch(\Exception $e) {
            $this->get('logger')->critical($e);
        }

        return $response->setPublic();
    }

    public function topMembresAction()
    {
        $siteManager = $this->container->get("site_manager");
        $site = $siteManager->getCurrentSite();

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNUserBundle:User");

        $response = $this->render("TBNAgendaBundle:Hinclude:membres.html.twig", [
            "membres" => $repo->findTopMembres($site)
        ]);

        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic()
        ;
    }
}
