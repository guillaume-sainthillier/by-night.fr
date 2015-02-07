<?php

namespace TBN\MajDataBundle\Parser;

use Symfony\Component\DomCrawler\Crawler;
use TBN\AgendaBundle\Repository\AgendaRepository;

/**
 * Description of BikiniParser
 *
 * @author guillaume
 */
class SoonNightParser extends LinksParser{

    protected $base_url;

    public function __construct(AgendaRepository $repo, $url) {
        parent::__construct($repo, $url);

        $this->base_url = "http://www.soonnight.com";
        return $this;
    }

    protected function parseDate($date)
    {
        $tabMois = ["janvier","fevrier","mars","avril","mai","juin","juillet","aout","septembre","octobre","novembre","decembre"];

        return preg_replace_callback("/(.+)(\d{2}) (".implode("|", $tabMois).") (\d{4})(.+)/iu",
                function($items) use($tabMois)
        {
            return $items[4]."-".(array_search(strtolower($items[3]),$tabMois) +1)."-".$items[2];
        }, $date);
    }

    protected function decodeNumCharacter($t)
    {
        $convmap = array(0x0, 0x2FFFF, 0, 0xFFFF);
        return mb_decode_numericentity($t, $convmap, 'UTF-8');
    }

    /**
     * Retourne les infos d'un agenda depuis une url
     * @return string[]
     */
    protected function getInfosAgenda()
    {
        $tab_retour = [];

        //Date & Nom
        $date_lieu                      = preg_split("/-/",$this->parser->filter(".titre h2")->text());
        $nom                            = preg_replace("/\&(\d+);/i", "&#$1;", $this->parser->filter(".titre h1")->text())." @ ".$date_lieu[1];
        $tab_retour["nom"]              = $this->decodeNumCharacter($nom);
        $tab_retour["lieu_nom"]         = $date_lieu[1];
        $date                           = $this->parseDate($date_lieu[0]);

        $tab_retour["date_debut"]       = \DateTime::createFromFormat("Y-n-d",$date);
        $tab_retour["date_affichage"]   = $tab_retour["date_debut"] !== false ? "Le ".$tab_retour["date_debut"]->format("d/m/Y") : "NP";
        
        //Lieux
        $rue                            = null;
        $code_postal                    = null;
        $ville                          = null;
        $lieux                          = $this->getNodeFromHeading($this->parser->filter(".lieu"));
        if($lieux)
        {
            $node_rue                       = $lieux->filter("span[property='v:street-address']");
            $node_code_postal               = $lieux->filter("span[property='v:postal-code']");
            $node_ville                     = $lieux->filter("span[property='v:locality']");

            $rue                            = $node_rue->count() ? trim($node_rue->text()) : null;
            $code_postal                    = $node_code_postal->count() ? trim($node_code_postal->text()) : null;
            $ville                          = $node_ville->count() ? trim($node_ville->text()) : null;
        }
        $tab_retour["rue"]		    = $rue;
        $tab_retour["code_postal"]          = $code_postal;
        $tab_retour["commune"]              = $ville;

        //Téléphone & Tarifs
        $telephone                          = null;        
        $infoline                           = $this->getNodeFromHeading($this->parser->filter(".infoline"));
        if($infoline)
        {
            $telephone = $infoline->text();
        }
        $tab_retour["reservation_telephone"]    = $telephone;

        $tarifs                                 = $this->getNodeFromHeading($this->parser->filter(".prix"));
        $tab_retour["tarif"]                    = $tarifs ? trim($tarifs->text()) : null;

        //Description
        $descriptif_long                        = $this->parser->filter("#bloc_texte span[property='v:description']");
        $descriptif                             = null;

        //Suppression des foutues pubs
        foreach($descriptif_long as $node)
        {
            foreach($node->childNodes as $children)
            {                
                if($children->nodeType === XML_TEXT_NODE || ! in_array($children->nodeName, ['span', 'div']))
                {
                    $descriptif .= ($children->nodeName === "br" ? "<br>" : $children->textContent." ");
                }
            }            
        }

        $black_list = [
            "Toute l'équipe répond à vos questions au ",
            "Réservation et complément d'information au ",
            "réservation simple et rapide au ",
            "Afficher le numéro du service de mise en relation"
        ];
        $clean_descriptif                       = str_replace($black_list,"",$descriptif);
        $tab_retour["descriptif"]               = $clean_descriptif;

        //Catégorie & Thème
        $node_categorie                         = $this->getNodeFromHeading($this->parser->filter(".genre"));
        $tab_retour["categorie"]                = $node_categorie ? trim($node_categorie->text()) : null;

        $node_musique                           = $this->getNodeFromHeading($this->parser->filter(".musique"));
        $tab_retour["musique"]                  = $node_musique ? trim($node_musique->text()) : null;

        //Image
        $image                                  = $this->parser->filter(".case_visuel img");
        $tab_retour["image"]                    = $image->count() ? $image->attr("src") : null;
        $tab_retour["source"]                   = $this->url;

        return $tab_retour;
    }

    protected function getNodeFromHeading(Crawler $heading)
    {
        $node = null;

        if($heading->count())
        {
            $parents = $heading->eq(($heading->count() - 1))->parents();
            if($parents->count())
            {
                $siblings = $parents->eq(0)->siblings();
                if($siblings->count())
                {
                    return $siblings->eq(0);
                }
            }
        }
        
        return $node;
    }

    public function getLinks()
    {
        $this->parseContent("HTML");
        $base_url = $this->base_url;
        return $this->parser->filter("div.affichage_liste_1 a.titre")->each(function(Crawler $item) use($base_url)
        {
            return $base_url.$item->attr("href");
        });
    }

    public function hydraterAgenda($infos_agenda) {

        $tab_champs = $infos_agenda;

        $dateDebut = $tab_champs["date_debut"];

        $nom = $tab_champs["nom"];
        $a = $this->getAgendaFromUniqueInfo($nom, $dateDebut, null, $tab_champs["lieu_nom"]);

        $a->setNom($nom);
        $a->setLieuNom($tab_champs["lieu_nom"]);
        $a->setDateDebut($dateDebut);
        $a->setVille($tab_champs["commune"]);
        $a->setRue($tab_champs["rue"]);
        $a->setCommune($tab_champs["commune"]);
        $a->setVille($tab_champs["commune"]);
        $a->setCodePostal($tab_champs["code_postal"]);
        $a->setTarif($tab_champs["tarif"]);
        $a->setReservationTelephone($tab_champs["reservation_telephone"]);
        $a->setDescriptif($tab_champs["descriptif"]);
        $a->setTypeManifestation("Musique");
        $a->setCategorieManifestation($tab_champs["categorie"]);
        $a->setThemeManifestation("Musique,".$tab_champs["musique"]);
        $a->setUrl($tab_champs["image"]);
        $a->setSource($tab_champs["source"]);

        return $a;
    }

    public function getNomData() {
        return "SoonNight";
    }
}
