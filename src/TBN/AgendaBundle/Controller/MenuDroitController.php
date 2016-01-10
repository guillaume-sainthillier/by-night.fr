<?php

namespace TBN\AgendaBundle\Controller;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;
use TBN\MainBundle\Controller\TBNController as Controller;
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

    /**
     * @Cache(expires="tomorrow", public=true)
     */
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

    /**
     * @Cache(expires="tomorrow", public=true)
     */
    public function topSoireesAction() {
        $siteManager = $this->container->get("site_manager");
        $site = $siteManager->getCurrentSite();

        return $this->render("TBNAgendaBundle:Hinclude:evenements.html.twig", [
            "soirees" => $this->getTopSoirees($site)
        ]);
    }

    public function tendancesAction(Agenda $soiree) {

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

    /**
     * @Cache(expires="tomorrow", public=true)
     */
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

    /**
     * @Cache(expires="+6 hours", public=true)
     */
    public function topMembresAction() {
        $siteManager = $this->container->get("site_manager");
        $site = $siteManager->getCurrentSite();
        
        return $this->render("TBNAgendaBundle:Hinclude:membres.html.twig", [
            "membres" => $this->getTopMembres($site)
        ]);
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

}
