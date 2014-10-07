<?php

namespace TBN\MajDataBundle\Parser;

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
            return $items[4]."-".(array_search($items[3],$tabMois) +1)."-".$items[2];
        }, $date);
    }

    /**
     * Retourne les infos d'un agenda depuis une url
     * @return string[]
     */
    protected function getInfosAgenda()
    {
        $tab_retour = [];

        $date_lieu                      = preg_split("/-/",$this->parser->filter(".titre h2")->text());
        $tab_retour["nom"]              = $this->parser->filter(".titre h1")->text()." @ ".$date_lieu[1];
        $tab_retour["lieu_nom"]         = $date_lieu[1];
        $date                           = $this->parseDate($date_lieu[0]);
        $tab_retour["date_debut"]       = \DateTime::createFromFormat("Y-n-d",$date);
        $tab_retour["date_affichage"]   = $tab_retour["date_debut"] !== false ? "Le ".$tab_retour["date_debut"]->format("d/m/Y") : "NP";
        $adresses                       = $this->parser->filter(".lieu")->each(function($e){ return $e->parents()->eq(0)->siblings()->eq(0); });

        foreach($adresses as $adresse)
        {
            $infos                          = $adresse->filter("span");
            $tab_retour["rue"]		    = implode("",$infos->reduce(function($e){ return ($e->attr("property") == "v:street-address"); })->each(function($e){ return $e->text(); }));
            $tab_retour["code_postal"]      = implode("",$infos->reduce(function($e){ return $e->attr("property") == "v:postal-code" ; })->each(function($e){ return $e->text(); }));
            $tab_retour["commune"]          = implode("",$infos->reduce(function($e){ return $e->attr("property") == "v:locality" ; })->each(function($e){ return $e->text(); }));
        }

        $tab_retour["tarif"]			= implode("",$this->parser->filter(".prix")->each(function($e){ return $e->parents()->eq(0)->siblings()->eq(0)->text(); }));
        $tab_retour["reservation_telephone"]    = trim(implode("",$this->parser->filter(".infoline")->eq(0)->each(function($e){ return $e->parents()->eq(0)->siblings()->eq(0)->filter("span")->eq(0)->text(); })));
        $descriptif_long                        = $this->parser->filter("#bloc_texte span")->reduce(function($e){ return $e->attr("id") != "infoline3"; });

        //Suppression des foutues pubs
        $pubs = $descriptif_long->filter("div.case_infos");

        $full_descriptif = trim(implode("",$pubs->each(function($pub) use($descriptif_long)
        {
            return strip_tags(str_replace($pub->html(), "", $descriptif_long->html()),"<br>");
        })));

        $tab_retour["descriptif"]               = str_replace(" Réservation et complément d'information au Afficher le numéro du service de mise en relation ","",$full_descriptif);
        $tab_retour["descriptif"]               = str_replace("Réservations :  Afficher le numéro du service de mise en relation","",$tab_retour["descriptif"]);
        $tab_retour["descriptif"]               = str_replace("réservation simple et rapide au Afficher le numéro du service de mise en relation","",$tab_retour["descriptif"]);
        $tab_retour["descriptif"]               = str_replace("Afficher le numéro du service de mise en relation","",$tab_retour["descriptif"]);
        $tab_retour["categorie"]                = implode("",$this->parser->filter(".genre")->each(function($e){ return $e->parents()->eq(0)->siblings()->eq(0)->text(); }));
        $tab_retour["musique"]                  = implode("",$this->parser->filter(".musique")->each(function($e){ return $e->parents()->eq(0)->siblings()->eq(0)->text(); }));
        $tab_retour["image"]                    = $this->parser->filter(".case_visuel img")->attr("src");

        return $tab_retour;
    }

    public function getLinks()
    {
        $this->parseContent("HTML");
        $base_url = $this->base_url;
        return $this->parser->filter("div.affichage_liste_1 a.titre")->each(function($item) use($base_url)
        {
            return $base_url.$item->attr("href");
        });
    }

    public function hydraterAgenda($infos_agenda) {

        $tab_champs = $infos_agenda;

        $dateDebut = $tab_champs["date_debut"];

        if($dateDebut === false)
        {
            var_dump("Erreur", $dateDebut);
        }

        $nom = $tab_champs["nom"];

        $a = $this->getAgendaFromUniqueInfo($nom, $dateDebut);

        $a->setNom($nom);
        $a->setLieuNom($tab_champs["lieu_nom"]);
        $a->setDateDebut($dateDebut);
        $a->setVille($tab_champs["commune"]);
        $a->setRue($tab_champs["rue"]);
        $a->setCommune($tab_champs["commune"]);
        $a->setCodePostal($tab_champs["code_postal"]);
        $a->setTarif($tab_champs["tarif"]);
        $a->setReservationTelephone($tab_champs["reservation_telephone"]);
        $a->setDescriptif($tab_champs["descriptif"]);
        $a->setTypeManifestation("Musique");
        $a->setCategorieManifestation($tab_champs["categorie"]);
        $a->setThemeManifestation("Musique,".$tab_champs["musique"]);
        $a->setUrl($tab_champs["image"]);

        return $a;
    }

    public function getNomData() {
        return "SoonNight";
    }
}
