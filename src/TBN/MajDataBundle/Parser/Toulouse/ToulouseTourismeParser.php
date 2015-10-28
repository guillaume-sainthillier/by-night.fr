<?php

namespace TBN\MajDataBundle\Parser\Toulouse;

use TBN\MajDataBundle\Parser\LinksParser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Description of ToulouseTourismeParser
 *
 * @author guillaume
 */
class ToulouseTourismeParser extends LinksParser {

    public function __construct() {
	parent::__construct();
        
        $this->setUrl('http://www.toulouse-tourisme.com/offre/recherche/Sur-place/Agenda//~~~/page-1');
	$this->setBaseUrl('http://www.toulouse-tourisme.com/');
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

	    $tab_retour['date_fin'] = \DateTime::createFromFormat('d/m/Y', $date_fin) ?: null;
	}


	//Tarifs
	$tarifs			    = $this->parser->filter('.tarifs .main_info');
	$tarif			    = $tarifs->count() ? $tarifs->text() : null;
	if($tarif === 'Gratuit')
	{
	    $tarif = null;
	}

	//Réservations Internet & Téléphone
	$infos_resa = $this->parser->filter('ul.children')->eq(0)->filter('li')->each(function(Crawler $info)
	{
	    return $info->text();
	});

	$resa_internet = [];
	$resa_telephone = [];

	foreach($infos_resa as $info_resa)
	{
	    $info_resa = trim($info_resa);
	    if(filter_var('http://'.$info_resa, FILTER_VALIDATE_URL))
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


	$tab_retour['date_debut']		= \DateTime::createFromFormat('d/m/Y', $date_debut) ?: null;
	$tab_retour['url']			= $this->parser->filter('#pictures_img .smoothbox')->count() ? $this->parser->filter('#pictures_img .smoothbox')->attr('href') : null;
	$tab_retour['nom']			= $this->parser->filter('h1.gotham_black')->count() ? $this->parser->filter('h1.gotham_black')->text() : null;
	$tab_retour['theme_manifestation']      = $this->parser->filter('.localisation')->count() ? $this->parser->filter('.localisation')->eq(0)->text() : null;
	$tab_retour['place.nom']		= $lieu;
	$tab_retour['place.rue']		= $rue;
	$tab_retour['place.codePostal']         = $cp;
	$tab_retour['place.ville']		= $ville;
	$tab_retour['descriptif']		= $description_start.' '.$description_end;
	$tab_retour['reservation_telephone']	= implode(',', $resa_telephone);
	$tab_retour['reservation_internet']	= implode(',', $resa_internet);
	$tab_retour['tarif']			= $tarif;
        $tab_retour['source']                   = $this->url;

	return $tab_retour;
    }

    public function getLinks() {
	$this->parseContent('HTML');

	$urls = [];
	while($this->url !== null)
	{
	    $events = $this->parser->filter(".list_results .link_parent");
	    $urls = array_merge($urls, $events->each(function(Crawler $item) {
		return $this->getBaseUrl() . $item->filter("a.link_block")->attr("href");
	    }));

	    $next = $this->parser->filter("#pagenavigator .next a");
	    if($next->count() > 0)
	    {
		$this->setUrl($this->getBaseUrl().$next->eq(0)->attr("href"));
		$this->parseContent();
	    }else
	    {
		$this->url = null;
	    }
	}
	return $urls;
    }

    public function getNomData() {
	return "ToulouseTourisme";
    }
}
