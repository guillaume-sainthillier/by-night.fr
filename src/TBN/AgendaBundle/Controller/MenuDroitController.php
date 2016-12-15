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
class MenuDroitController extends Controller
{
    const FB_MEMBERS_LIMIT = 50;

    public function programmeTVAction()
    {
        $parser = $this->get("tbn.programmetv");
        $programmes = array_map(function ($programme) {
            $css_chaine = $this->getCSSChaine($programme['chaine']);
            $programme['css_chaine'] = $css_chaine ? 'icon-' . $css_chaine : null;
            return $programme;
        }, $parser->getProgrammesTV());

        $response = $this->render("TBNAgendaBundle:Hinclude:programme_tv.html.twig", [
            "programmes" => $programmes
        ]);

        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic()
        ;
    }

    protected function getCSSChaine($chaine)
    {
        switch ($chaine) {
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
            case 'C8':
                return 'canal8';
            case 'W9':
                return 'w9';
            case 'TMC':
                return 'tmc';
            case 'NT1':
                return 'nt1';
            case 'NRJ 12':
                return 'nrj';
            case 'LCP - Public Sénat':
                return 'lcp';
            case 'CStar':
                return 'cstar';
            case 'France 4':
                return 'france4';
            case 'BFM TV':
                return 'bfm_tv';
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
            case "L'Equipe":
                return 'lequipe';
            case "Franceinfo":
                return 'franceinfo';
            case "LCI":
            case "LCI - La Chaîne Info":
                return 'lci';
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
            case 'TV5MONDE':
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

    public function soireesSimilairesAction(Agenda $soiree, $page = 1)
    {
        if ($page <= 0) {
            $page = 1;
        }

        $offset = 7;

        $response = $this->render("TBNAgendaBundle:Hinclude:evenements.html.twig", [
            "soirees" => $this->getSoireesSimilaires($soiree, $page, $offset),
            "maxItems" => $offset
        ]);


        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic()
        ;
    }

    public function topSoireesAction()
    {
        $siteManager = $this->container->get("site_manager");
        $site = $siteManager->getCurrentSite();

        $response = $this->render("TBNAgendaBundle:Hinclude:evenements.html.twig", [
            "soirees" => $this->getTopSoirees($site)
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
        if(count($retour['interets']) == self::FB_MEMBERS_LIMIT || count($retour['participations'])) {
            $maxItems = count($membres);
        }else {
            $maxItems = 0;
        }

        $response = $this->render("TBNAgendaBundle:Hinclude:fb_membres.html.twig", [
            "membres" => $membres,
            "maxItems" => $maxItems
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

        $response = $this->render("TBNAgendaBundle:Hinclude:membres.html.twig", [
            "membres" => $this->getTopMembres($site)
        ]);

        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic()
        ;
    }

    protected function getTopSoirees(Site $site)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('TBNAgendaBundle:Agenda');

        return $repo->findTopSoiree($site);
    }

    protected function getTopMembres(Site $site)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNUserBundle:User");
        return $repo->findTopMembres($site);
    }

    protected function getSoireesSimilaires(Agenda $soiree, $page, $offset)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        return $repo->findAllSimilaires($soiree, $page, $offset);
    }
}
