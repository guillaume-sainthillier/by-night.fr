<?php

namespace TBN\AgendaBundle\Controller;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\Filesystem\Filesystem;

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
        
        $fileSystem = new Filesystem;
        $parser = $this->get("tbn.programmetv");

        $programmes     = $parser->getProgrammesTV();        
        $webPath        = $this->get('kernel')->getRootDir() . '/../web';
        $relativePath   = '/uploads/programmes';
        $absolutePath   = $webPath . $relativePath;
        
        $fileSystem->mkdir($absolutePath, 0755);
        foreach($programmes as & $programme)
        {
            if($programme['logo'])
            {
                $pathFile   = '/' . md5($programme['logo']) . '.gif';
                $absolutePathFile = $absolutePath . $pathFile;
                if(! $fileSystem->exists($absolutePathFile))
                {
                    $fileSystem->copy($programme['logo'], $absolutePathFile);
                }
                $programme['asset'] = $relativePath . $pathFile;
            }
        }
        
        return $this->render("TBNAgendaBundle:Hinclude:programme_tv.html.twig", [
            "programmes" => $programmes
        ]);
    }

    //Pas de cache par défaut
    public function soireesSimilairesAction(Agenda $soiree, $page) {
        if($page <= 0)
        {
            $page = 1;
        }

        $offset = 7;

        return $this->render("TBNAgendaBundle:Hinclude:evenements.html.twig", [
            "soirees" => $this->getSoireesSimilaires($soiree, $page, $offset),
            "maxItems" => $offset
        ]);
    }

    //Pas de cache par défaut
    public function topSoireesAction() {
        $siteManager = $this->container->get("site_manager");
        $site = $siteManager->getCurrentSite();

        return $this->render("TBNAgendaBundle:Hinclude:evenements.html.twig", [
                    "soirees" => $this->getTopSoirees($site)
        ]);
    }

    public function tendancesAction(Agenda $soiree) {
        $this->getFBStatsEvent($soiree);

        $nbItems    = 30;
        $membres    = $this->getFBMembres($soiree, 1, $nbItems);

        return $this->render("TBNAgendaBundle:Hinclude:tendances.html.twig", [
            "tendancesParticipations" => $this->getSoireesTendancesParticipations($soiree),
            "tendancesInterets" => $this->getSoireesTendancesInterets($soiree),
            "count_participer" => $soiree->getParticipations() + $soiree->getFbParticipations(),
            "count_interets" => $soiree->getInterets() + $soiree->getFbInterets(),
            "membres" => $membres,
            "maxItems" => $nbItems
        ]);
    }

    public function fbMembresAction(Agenda $soiree, $page)
    {
        if($page <= 1)
        {
            $page = 2;
        }
        
        $nbItems    = 50;
        $membres    = $this->getFBMembres($soiree, $page, $nbItems);

        return $this->render("TBNAgendaBundle:Hinclude:fb_membres.html.twig", [
            "membres" => $membres,
            "maxItems" => $nbItems
        ]);
    }

    protected function getFBMembres(Agenda $soiree, $page, $offset)
    {
        $membres = [];
        if ($soiree->getFacebookEventId()) {
            $key = "fb.stats." . $soiree->getFacebookEventId();
            $cache = $this->get("winzou_cache");
            if ($cache->contains($key)) {
                $membres = $cache->fetch($key);
            }
        }

        return array_slice($membres, ($page - 1) * $offset, $offset);
    }

    //Pas de cache par défaut ici
    public function topMembresAction(Request $request) {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNUserBundle:User");
        $siteManager = $this->container->get("site_manager");
        $site = $siteManager->getCurrentSite();
        $str_date = $repo->getLastDateUser($site);

        $response = $this->cacheVerif($str_date);
        if ($response !== null && $response->isNotModified($request)) {
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
            if ($dateModification <= $today || $soiree->getFbParticipations() === null || $soiree->getFbInterets() === null) {
                $api    = $this->get("tbn.social.facebook_admin");
                $retour = $api->getEventStats($id);
                $cache  = $this->get("winzou_cache");
                $cache->save("fb.stats." . $id, $retour["membres"]);

                $soiree->setFbInterets($retour["nbInterets"]);
                $soiree->setFbParticipations($retour["nbParticipations"]);
                $soiree->setDateModification(new \DateTime);

                $em = $this->getDoctrine()->getManager();
                $em->persist($soiree);
                $em->flush();
            }
        }
    }

    protected function getTopSoirees(Site $site) {
        $em	    = $this->getDoctrine()->getManager();
        $repo	    = $em->getRepository('TBNAgendaBundle:Agenda');
        
        return $repo->findTopSoiree($site);
    }

    protected function getTopMembres(Site $site) {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        return $repo->findTopMembres($site);
    }

    protected function getSoireesSimilaires(Agenda $soiree, $page, $offset) {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        return $repo->findAllSimilaires($soiree, $page, $offset);
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
