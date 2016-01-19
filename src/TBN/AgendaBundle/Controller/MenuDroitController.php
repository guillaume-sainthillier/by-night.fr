<?php

namespace TBN\AgendaBundle\Controller;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;
use TBN\MainBundle\Controller\TBNController as Controller;
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

        $programmes     = array_map(function($programme) {
            $css_chaine = $this->getCSSChaine($programme['chaine']);
            $programme['css_chaine'] = $css_chaine ? 'icon-'.$css_chaine : null;
            return $programme;
        }, $parser->getProgrammesTV());
        
        return $this->render("TBNAgendaBundle:Hinclude:programme_tv.html.twig", [
            "programmes" => $programmes
        ]);
    }
    
    protected function getCSSChaine($chaine) {
        switch($chaine) {
            case 'TF1':
                return 'tf1';
            case 'France 2':
                return 'france2';
            case 'France 3':
                return 'france3';
            case 'Canal+':
                return 'canal_plus';
            case 'Arte':
                return 'arte';
            case 'M6':
                return 'm6';
            case 'France 5':
                return 'france5';
            case 'D8':
                return 'd8';
            case 'W9':
                return 'w9';
            case 'TMC':
                return 'tmc';
            case 'NT1':
                return 'nt1';
            case 'NRJ 12':
                return 'nrj';
            case 'LCP-AN / Public Sénat':
                return 'lcp';
            case 'France 4':
                return 'france4';
            case 'BFM TV':
                return 'bfm';
            case 'i>Télé':
                return 'itele';
            case 'D17':
                return 'd17';
            case 'Gulli':
                return 'gulli';
            case 'France Ô':
                return 'franceo';
            case 'HD1':
                return 'hd1';
            case "L'Equipe 21":
                return 'lequipe';
            case '6ter':
                return '6ter';
            case 'Numéro 23':
                return 'numero23';
            case 'RMC Découverte':
                return 'rmc';
            case 'Chérie 25':
                return 'cherie25';
            case 'IDF1':
                return 'idf';
            case 'Canal partagé':
                return 'canal_partage';
            case 'RTL 9':
                return 'rtl9';
            case 'Paris Première':
                return 'paris_premiere';
            case 'Plug RTL':
                return 'plug_rtl';
            case 'TV5 Monde':
                return 'tv5_monde';
            case '13e rue':
                return '13_rue';
            case 'E ! Entertainment':
                return 'e_entertainment';
            case 'Syfy':
                return 'syfy';
            case 'Série club':
                return 'serie_club';
            case 'Nat Geo Wild':
                return 'nat_geo';
        }
        
        return null;
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
