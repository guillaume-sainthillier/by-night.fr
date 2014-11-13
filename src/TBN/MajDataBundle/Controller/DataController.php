<?php

namespace TBN\MajDataBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use TBN\MajDataBundle\Parser\BikiniParser;
use TBN\MajDataBundle\Parser\DynamoParser;
use TBN\MajDataBundle\Parser\ToulouseParser;
use TBN\MajDataBundle\Parser\SoonNightParser;
use TBN\MajDataBundle\Parser\FaceBookParser;
use TBN\MajDataBundle\Parser\ToulouseTourismeParser;
use TBN\MajDataBundle\Parser\ParisTourismeParser;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\MajDataBundle\Entity\HistoriqueMaj;
use TBN\MainBundle\Entity\Site;

class DataController extends Controller
{

    public function flushAction(Site $site)
    {
	$em             = $this->getDoctrine()->getManager();
        $repo           = $em->getRepository("TBNAgendaBundle:Agenda");
	$agendas	= $repo->findBy(["site" => $site], ["dateDebut" => "ASC"]);

	$nbSpam = 0;
	foreach($agendas as $agenda)
	{
	    if($this->isSpam($agenda))
	    {
		$em->remove($agenda);
		$nbSpam++;
	    }
	}

	$this->get('session')->getFlashBag()->add(
	    'success',
            '<strong>'.$nbSpam.'</strong> suppressions'
        );

	$em->flush();

	return $this->redirect($this->generateUrl("tbn_main_index"));
    }

    public function indexAction($site, Site $currentSite)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $parserManager  = $this->get("parser_manager");
        $em             = $this->getDoctrine()->getManager();
        $repo           = $em->getRepository("TBNAgendaBundle:Agenda");
        $repoBL         = $em->getRepository("TBNMajDataBundle:BlackList");
	$env		= $this->get('kernel')->getEnvironment();

        $getter = "parse".lcfirst($currentSite->getSubdomain());
        $this->$getter($parserManager, $repo, $repoBL, $currentSite, $site);

        $this->persistAgendas($em, $currentSite, $parserManager, $site, $env);

	if($env !== "prod")
	{
	    die("OK");
	}

        return $this->redirect($this->generateUrl("tbn_main_index"));
    }

    protected function parseParis($parserManager, $repo, $repoBL, $currentSite, $site)
    {
        if(!$site or $site === "soonight")
        {
            $parserManager->addAgendaParser(new SoonNightParser($repo, $this->container->getParameter("url_soonnight_paris")));
        }

        if(!$site or $site === "tourisme")
        {
            $parserManager->addAgendaParser(new ParisTourismeParser($repo));
        }

	if(!$site or $site === "facebook")
        {
	    $geocoder = $this->get('ivory_google_map.geocoder');
            $siteManager = $this->get("site_manager");
            $parserManager->addAgendaParser(new FaceBookParser($repo, $repoBL, $this->get("tbn.social.facebook"), $currentSite, $siteManager, $geocoder));
        }
    }

    protected function parseToulouse($parserManager, $repo, $repoBL, $currentSite, $site)
    {
        if(!$site or $site === "toulouse")
        {
            $url = "http://data.grandtoulouse.fr/web/guest/les-donnees/-/opendata/card/21905-agenda-des-manifestations-culturelles/resource/document?p_p_state=exclusive&_5_WAR_opendataportlet_jspPage=%2Fsearch%2Fview_card_license.jsp";
            $parserManager->addAgendaParser(new ToulouseParser($repo, $url));
        }

        if(!$site or $site === "bikini")
        {
            $parserManager->addAgendaParser(new BikiniParser($repo, $this->container->getParameter("url_bikini")));
        }

        if(!$site or $site === "dynamo")
        {
            $parserManager->addAgendaParser(new DynamoParser($repo, $this->container->getParameter("url_dynamo")));
        }

        if(!$site or $site === "tourisme")
        {
            $parserManager->addAgendaParser(new ToulouseTourismeParser($repo));
        }

        if(!$site or $site === "soonight")
        {
            $parserManager->addAgendaParser(new SoonNightParser($repo, $this->container->getParameter("url_soonnight_tlse")));
        }

        if(!$site or $site === "facebook")
        {
	    $geocoder = $this->get('ivory_google_map.geocoder');
            $siteManager = $this->get("site_manager");
            $parserManager->addAgendaParser(new FaceBookParser($repo, $repoBL, $this->get("tbn.social.facebook"), $currentSite, $siteManager, $geocoder));
        }
    }

    /**
     * Retourne la recherche d'un doublon en fonction de la pertinance des informations
     * @param Agenda $event l'événement à rechercher
     * @param Agenda[] $agendas l'événement à rechercher
     * @return boolean vrai si un événement similaire est déjà présent, faux sinon
    */
    protected function hasSimilarEvent(Agenda $event, $agendas)
    {
	$clean_descriptif_event     = strtolower(preg_replace("/[^a-zA-Z0-9]+/u", " ", html_entity_decode($event->getDescriptif())));
	$nom_event                  = $event->getNom();
	$date_debut_event	    = $event->getDateDebut();

	if(strlen($clean_descriptif_event) <= 50) //Moins de 70 caractères, on l'ejecte
	{
	    return true;
	}

        foreach($agendas as $agenda)
        {
            $date_debut_needle  = $agenda->getDateDebut();
            if($date_debut_event->format("Y-m-d") === $date_debut_needle->format("Y-m-d"))
            {
                $nom_needle                 = $agenda->getNom();
                if(similar_text($nom_event, $nom_needle) > 70) // Plus de 70% de ressemblance, on l'ejecte
                {
                    return true;
                }   

                if(stristr($nom_event, $nom_needle) !== false or stristr($nom_needle, $nom_event) !== false)
                {
                    return true;
                }
            }
        }

        return $this->isSpam($event);
    }

    protected function isSpam($agenda)
    {
	//Vérification des events spams
        $black_list = [
	    "Buy and sell tickets at","Please join","Invite Friends","Buy Tickets",
	    "Find Local Concerts", "reverbnation.com", "pastaparty.com", "evrd.us",
	    "farishams.com", "tinyurl.com", "bandcamp.com", "ty-segall.com",
	    "fritzkalkbrenner.com", "campusfm.fr", "polyamour.info", "parislanuit.fr",
	    "Please find the agenda", "Fore More Details like our Page & Massage us"
	];


	$terms = array_map(function($term)
	{
	    return preg_quote($term);
	}, $black_list);

        return preg_match("/".implode("|", $terms)."/i", $agenda->getDescriptif());
    }

    protected function cleanEvents($agendas)
    {
	$clean_agendas = [];
	foreach($agendas as $agenda)
	{
	    if(! $this->hasSimilarEvent($agenda, $clean_agendas))
	    {
		$clean_agendas[] = $this->cleanEvent($agenda);
	    }
	}

	return $clean_agendas;
    }

    protected function cleanEvent($agenda)
    {
	if(in_array(strtolower($agenda->getTarif()), ["gratuit"]))
	{
	    $agenda->setTarif(null);
	}
	$descriptif = $this->strip_tags($this->strip_style($agenda->getDescriptif()));
	return $agenda->setDescriptif($descriptif);
    }

    protected function persistAgendas($em, Site $site, $parserManager, $parser, $env)
    {
        $agendas	    = $this->cleanEvents($parserManager->parse());
        $blackLists         = $parserManager->getBlackLists();
        $nbNewSoirees	    = 0;
        $nbUpdateSoirees    = 0;

        foreach($agendas as $agenda)
        {
            if($agenda->getNom() !== null and trim($agenda->getNom()) !== "")
            {
                if($agenda->getId() === null)
                {
                    $nbNewSoirees++;
                }else
                {
                    $nbUpdateSoirees++;
                }

                if($env === "prod" and $agenda->getPath() === null and $agenda->getUrl() !== null) // On récupère l'image distante
                {
                    $this->downloadImage($agenda);
                }

                if($agenda->getSite() === null)
                {
                    $agenda->setSite($site);
                }

                $em->persist($agenda);
            }
        }

        foreach($blackLists as $blackList)
        {
            $em->persist($blackList);
        }

	$historique = new HistoriqueMaj();
	$historique->setFromData($parser)
		->setSite($site)
		->setNouvellesSoirees($nbNewSoirees)
		->setUpdateSoirees($nbUpdateSoirees)
                ->setBlackLists(count($blackLists));

        $em->persist($historique);
        $em->flush();
    }

    public function downloadImage(Agenda $agenda)
    {
        try
        {
            $url = preg_replace('/([^:])(\/{2,})/', '$1/', $agenda->getUrl());
            $agenda->setUrl($url);
            //En cas d'url du type:  http://u.rl/image.png?params
            $ext = preg_replace("/\?(.+)/", "", pathinfo($url, PATHINFO_EXTENSION));

            $filename = sha1(uniqid(mt_rand(), true)).".".$ext;
            $result = file_get_contents($url);

            if($result !== false)
            {
                // Save it to disk
                $savePath = $agenda->getUploadRootDir()."/".$filename;
                $fp = fopen($savePath,'x');

                if($fp !== false)
                {
                    fwrite($fp, $result);
                    fclose($fp);
                }
            }

            $agenda->setPath($filename);
        }catch(\Exception $e)
        {
            $agenda->setPath(null);
            $this->get("logger")->error($e->getMessage());
        }
    }

    protected function strip_tags($text)
    {
        return trim(htmlspecialchars_decode($text));
    }

    protected function strip_style($tag)
    {
        return preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/i",'<$1$2>', $tag);
    }

    /*
     * Retourne les données d'une URL
    */
    protected function get_data($url)
    {
        return \file_get_contents($url);
    }
}
