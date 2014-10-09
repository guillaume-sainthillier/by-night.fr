<?php

namespace TBN\AgendaBundle\Controller;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;

/**
 * Description of MenuDroitController
 *
 * @author guillaume
 */
class MenuDroitController extends Controller {

    /**
     * @Cache(expires="tomorrow", public=true)
     */
    public function programmeTVAction() {
        $parser = $this->get("tbn.programmetv");

        return $this->render("TBNAgendaBundle:Hinclude:programme_tv.html.twig", [
                    "programmes" => $parser->getProgrammesTV()
        ]);
    }

    //Pas de cache par défaut
    public function soireesSimilairesAction(Request $request, Agenda $soiree) {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        $str_date = $repo->getLastDateAutreSoirees($soiree);

        $response = $this->cacheVerif($str_date);
        if ($response !== null and $response->isNotModified($request)) {
            return $response;
        }

        return $response->setContent($this->renderView("TBNAgendaBundle:Hinclude:evenements.html.twig", [
                            "soirees" => $this->getSoireesSimilaires($soiree)
        ]));
    }

    //Pas de cache par défaut
    public function topSoireesAction(Request $request) {
        $siteManager = $this->container->get("site_manager");
        $site = $siteManager->getCurrentSite();

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        $str_date = $repo->getLastDateTopSoiree($site);

        $response = $this->cacheVerif($str_date);
        if ($response !== null and $response->isNotModified($request)) {
            return $response;
        }

        return $response->setContent($this->renderView("TBNAgendaBundle:Hinclude:evenements.html.twig", [
                            "soirees" => $this->getTopSoirees($site)
        ]));
    }

    //Pas de cache par défaut ici (à cause des détails FB)
    public function tendancesAction(Request $request, Agenda $soiree) {
        $this->getFBStatsEvent($soiree);

        $response = new Response();
        $response->setPublic()
                ->setLastModified($soiree->getDateModification());

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response->setContent($this->renderView("TBNAgendaBundle:Hinclude:tendances.html.twig", [
                            "tendancesParticipations" => $this->getSoireesTendancesParticipations($soiree),
                            "tendancesInterets" => $this->getSoireesTendancesInterets($soiree),
                            "count_participer" => $soiree->getParticipations() + $soiree->getFbParticipations(),
                            "count_interets" => $soiree->getInterets($soiree) + $soiree->getFbInterets()
        ]));
    }

    //Pas de cache par défaut ici
    public function topMembresAction(Request $request) {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNUserBundle:User");
        $siteManager = $this->container->get("site_manager");
        $site = $siteManager->getCurrentSite();
        $str_date = $repo->getLastDateUser($site);

        $response = $this->cacheVerif($str_date);
        if ($response !== null and $response->isNotModified($request)) {
            return $response;
        }

        return $response->setContent($this->renderView("TBNAgendaBundle:Hinclude:membres.html.twig", [
                            "membres" => $this->getTopMembres($site)
        ]));
    }

    protected function cacheVerif($str_date) {
        $response = new Response();

        if ($str_date !== null) {
            //2014-05-08 11:49:21
            if (($date = \DateTime::createFromFormat("Y-m-d H:i:s", $str_date))) {
                $response->setPublic(); //Afin d'être partagée avec tout le monde
                $response->setLastModified($date);
            }
        }

        return $response;
    }

    protected function getFBStatsEvent(Agenda $soiree) {
        $id = $soiree->getFacebookEventId();
        if ($id !== null) {
            $dureeCache = 24;
            $dateModification = $soiree->getDateModification();
            $today = new \DateTime;
            $dateModification->modify("+" . $dureeCache . " hours");
            if ($dateModification < $today or $soiree->getFbParticipations() === null or $soiree->getFbInterets() === null) {
                $api = $this->get("tbn.social.facebook");
                $siteManager = $this->get("site_manager");
                $site = $siteManager->getCurrentSite();
                $em = $this->getDoctrine()->getManager();
                $retour = $api->getEventStats($siteManager->getSiteInfo(), $id);

                $soiree->setFbInterets($retour["interets"]);
                $soiree->setFbParticipations($retour["participations"]);
                $em->persist($soiree);
                $em->flush();
            }
        }
    }

    protected function getTopSoirees(Site $site) {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        return $repo->findTopSoiree($site);
    }

    protected function getTopMembres(Site $site) {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        return $repo->findTopMembres($site);
    }

    protected function getSoireesSimilaires(Agenda $soiree) {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        return $repo->findAllSimilaires($soiree);
    }

    protected function getSoireesTendancesParticipations(Agenda $soiree) {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        return $repo->findAllTendancesParticipations($soiree);
    }

    protected function getSoireesTendancesInterets(Agenda $soiree) {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        return $repo->findAllTendancesInterets($soiree);
    }
}
