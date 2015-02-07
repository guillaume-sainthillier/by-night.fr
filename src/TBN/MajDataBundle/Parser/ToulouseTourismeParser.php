<?php

namespace TBN\MajDataBundle\Parser;

use TBN\AgendaBundle\Repository\AgendaRepository;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Description of BikiniParser
 *
 * @author guillaume
 */
class ToulouseTourismeParser extends LinksParser {

    protected $base_url;

    public function __construct(AgendaRepository $repo) {
	parent::__construct($repo, "http://www.toulouse-tourisme.com/offre/recherche/Sur-place/Agenda//~~~/page-1");

	$this->base_url = "http://www.toulouse-tourisme.com/";
    }

    /**
     * Retourne les infos d'un agenda depuis une url
     * @return string[]
     */
    protected function getInfosAgenda() {

	$tab_retour = [];

	//Dates
	$nodes_date = $this->parser->filter(".localisation");

	if($nodes_date->count() === 3) // 2 description + 1 date
	{
	    $node_date = $nodes_date->eq(2);
	}elseif($nodes_date->count() === 2) // 1 description + 1 date
	{
	    $node_date = $nodes_date->eq(1);
	}else {
	    $node_date = $nodes_date->eq(0);
	}

	$dates = trim($node_date->text());
	$date_format_regex = "(\d{2})\/(\d{2})\/(\d{4})";

	if(preg_match("/le ".$date_format_regex."/i", $dates)) //le 27/09/2014
	{
	    //le 27/09/2014 -> 27/09/2014
	    $date_debut = preg_replace("/(.)+(".$date_format_regex.")/i","$2", $dates);
	}  else
	{
	    //du 27/09/2014 au 31/10/2014 -> 31/10/2014
	    $date_fin = preg_replace("/(.+)au (".$date_format_regex.")/i","$2", $dates);
	    //du 27 au 31/09/2014
	    if(($patterns = preg_match("/du (\d{2}) au ".$date_format_regex."/i", $dates)))
	    {
		//du 27 -> 27/09/2014
		$date_debut = preg_replace("/du (\d{2}) au ".$date_format_regex."/i","$1/$3/$4", $dates);
	    }else //du 27/09/2014 au 31/10/2014
	    {
		//du 27/09/2014 au 31/10/2014 -> 27/09/2014
		$date_debut = preg_replace("/du (".$date_format_regex.")(.+)/i","$1", $dates);
	    }

	    $tab_retour["date_fin"] = \DateTime::createFromFormat("d/m/Y", $date_fin);
	}


	//Tarifs
	$tarifs			    = $this->parser->filter(".tarifs .main_info");
	$tarif			    = $tarifs->count() ? $tarifs->text() : null;
	if($tarif === "Gratuit")
	{
	    $tarif = null;
	}

	//Réservations Internet & Téléphone
	$infos_resa = $this->parser->filter("ul.children")->eq(0)->filter("li")->each(function(Crawler $info)
	{
	    return $info->text();
	});

	$resa_internet = [];
	$resa_telephone = [];

	foreach($infos_resa as $info_resa)
	{
	    $info_resa = trim($info_resa);
	    if(filter_var("http://".$info_resa, FILTER_VALIDATE_URL))
	    {
		$resa_internet[] = $info_resa;
	    }else
	    {
		$resa_telephone[] = $info_resa;
	    }
	}

	//Description complète
	$description_start  = trim(preg_replace("/(\.\.\.)(\s*)$/i", "",$this->parser->filter("#start_desc")->text()));
	$description_end    = $this->parser->filter("#end_desc")->count() ? trim($this->parser->filter("#end_desc")->text()) : "";

	//Lieux
	$lieux = $this->parser->filter("ul.contact li");
	$rue = null;
	$lieu = $lieux->eq(0)->text();
	if($lieux->count() === 5) //La Rue est renseignée
	{
	    $rue = $lieux->eq(1)->text();
	    $cp_ville = $lieux->eq(3)->text();
	}elseif($lieux->count() === 4) //La Rue est renseignée
	{
	    $rue = $lieux->eq(1)->text();
	    $cp_ville = $lieux->eq(2)->text();
	}else
	{
	    $cp_ville = $lieux->eq(1)->text();
	}
	//31500 Toulouse -> 31500
	$cp = preg_replace("/^(\d+)(.+)/i","$1", $cp_ville);
	//31500 Toulouse -> Toulouse
	$ville = preg_replace("/^(\d+)(.+)/i","$2", $cp_ville);

        

	$tab_retour["date_debut"]		= \DateTime::createFromFormat("d/m/Y", $date_debut);
	$tab_retour["image"]			= $this->parser->filter("#pictures_img .smoothbox")->count() ? $this->parser->filter("#pictures_img .smoothbox")->attr("href") : null;
	$tab_retour["nom"]			= $this->parser->filter("h1.gotham_black")->count() ? $this->parser->filter("h1.gotham_black")->text() : null;
	$tab_retour["theme"]			= $this->parser->filter(".localisation")->count() ? $this->parser->filter(".localisation")->eq(0)->text() : null;
	$tab_retour["lieu"]			= $lieu;
	$tab_retour["rue"]			= $rue;
	$tab_retour["code_postal"]		= $cp;
	$tab_retour["ville"]			= $ville;
	$tab_retour["commune"]			= $ville;
	$tab_retour["description"]		= $description_start." ".$description_end;
	$tab_retour["reservation_telephone"]	= implode(",", $resa_telephone);
	$tab_retour["reservation_internet"]	= implode(",", $resa_internet);
	$tab_retour["tarif"]			= $tarif;
        $tab_retour["source"]                   = $this->url;

	return $tab_retour;
    }

    public function getLinks() {
	$this->parseContent("HTML");
	$base_url = $this->base_url;

	$urls = [];

	while($this->url !== null)
	{
	    $events = $this->parser->filter(".list_results .link_parent");
	    $urls = array_merge($urls, $events->each(function(Crawler $item) use($base_url) {
		return $base_url . $item->filter("a.link_block")->attr("href");
	    }));

	    $next = $this->parser->filter("#pagenavigator .next a");
	    if($next->count() > 0)
	    {
		$this->url = $base_url. $this->parser->filter("#pagenavigator .next a")->eq(0)->attr("href");
		$this->parseContent();
	    }else
	    {
		$this->url = null;
	    }
	}
	return $urls;
    }

    public function hydraterAgenda($infos_agenda) {

	$tab_champs = $infos_agenda;

	$dateDebut  = $tab_champs["date_debut"];
        $dateFin    = isset($tab_champs["date_fin"]) ? $tab_champs["date_fin"] : null;
	$nom	    = $tab_champs["nom"];
	$a	    = $this->getAgendaFromUniqueInfo($nom, $dateDebut, $dateFin, $tab_champs["lieu"]);

	if(isset($tab_champs["date_fin"]))
	{
	    $a->setDateFin($tab_champs["date_fin"]);
	}
	$a->setDateDebut($dateDebut);
	$a->setUrl($tab_champs["image"]);
	$a->setNom($nom);
	$a->setThemeManifestation($tab_champs["theme"]);
	$a->setLieuNom($tab_champs["lieu"]);
	$a->setRue($tab_champs["rue"]);
	$a->setCodePostal($tab_champs["code_postal"]);
	$a->setVille($tab_champs["ville"]);
	$a->setCommune($tab_champs["commune"]);
	$a->setDescriptif($tab_champs["description"]);
	$a->setReservationTelephone($tab_champs["reservation_telephone"]);
	$a->setReservationInternet($tab_champs["reservation_internet"]);
	$a->setTarif($tab_champs["tarif"]);
	$a->setSource($tab_champs["source"]);

	return $a;
    }

    public function getNomData() {
	return "ToulouseTourisme";
    }

}
