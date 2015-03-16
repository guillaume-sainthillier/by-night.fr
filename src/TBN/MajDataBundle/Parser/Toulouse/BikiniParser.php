<?php

namespace TBN\MajDataBundle\Parser\Toulouse;

use Symfony\Component\DomCrawler\Crawler;

use TBN\MajDataBundle\Parser\LinksParser;

/**
 *
 * @author Guillaume SAINTHILLIER
 */
class BikiniParser extends LinksParser {

    public function __construct() {
        parent::__construct();

        $this->setURL('http://www.lebikini.com/programmation/rss');
    }
    /**
     * Retourne les infos d'un agenda depuis une url
     * @return string[]
     */
    protected function getInfosAgenda()
    {
        $tab_retour = [];

        $date_affichage                             = $this->parseDate($this->parser->filter('#date')->text());
        $tab_retour['reservation_internet']         = implode(' ',$this->parser->filter('#reservation a.boutonReserverSpectacle')->each(function(Crawler $item) { return $item->attr('href'); }));
        $tab_retour['date_debut']                   = \DateTime::createFromFormat('Y-n-d', $date_affichage);
        $tab_retour['horaires']                     = preg_replace('/^(.+)à (\d{2}):(\d{2})$/i','A $2h$3.', $date_affichage);
        $tab_retour['nom']                          = $this->parser->filter('#blocContenu h2')->text();
        $tab_retour['place.nom']                    = $this->parser->filter('#salle h3')->text();
        $adresse                                    = $this->parser->filter('#salle #adresse')->html();
        $tab_retour['descriptif']                   = $this->parser->filter('#texte')->html();
        $tab_retour['url']                          = $this->parser->filter('#blocImage a[rel=shadowbox]')->attr('href');
        $tab_retour['source']                       = $this->url;
        $tab_retour['type_manifestation']           = 'Concert, Musique';

        /*
	 *  Rond point Madame de Mondonville Boulevard Netwiller
	    TOULOUSE
	 */
        $full_adresse = preg_split('/<br\/?>/i', $adresse);
	$ville = $full_adresse[1];
	if(preg_match('/\d/i', $ville))
	{
	    $tab_retour['place.ville.code_postal'] = preg_replace('/\D/i', '', $ville);
	    $ville = preg_replace('/\d/i', '', $ville);
	}

        $tab_retour['place.rue'] = $full_adresse[0];
        $tab_retour['place.ville.nom'] = $ville;

        $this->parser->filter('#blocContenu')->children()->each(function(Crawler $sibling) use(&$tab_retour)
        {
            if($sibling->attr('id') === 'prix')
            {
                $tab_retour['tarif'] = trim($sibling->text());
                return $sibling;
            }
            return false;
        });
        $this->parser->filter('#blocContenu')->children()->each(function(Crawler $sibling) use(&$tab_retour)
        {
            if($sibling->attr('id') === 'type')
            {
                $tab_retour['theme_manifestation'] = preg_replace('/style\s?:\s?/i', '', trim($sibling->text()));
                return $sibling;
            }
            return false;
        });

        return $tab_retour;
    }

    /**
     * Retourne les liens depuis le feed.xml
     * @return string[] le tableau des liens disponibles
     */
    public function getLinks()
    {
        $this->parseContent('XML');
        return $this->parser->filter('item')->each(function(Crawler $item)
        {
            return trim(preg_replace('/(.*)<link>(.*)<description>(.*)/im','$2',preg_replace('/\n/','',$item->html())));
        });
    }    

    public function getNomData() {
        return 'Bikini';
    }
}
