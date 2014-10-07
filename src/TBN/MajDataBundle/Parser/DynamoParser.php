<?php

namespace TBN\MajDataBundle\Parser;

use TBN\AgendaBundle\Repository\AgendaRepository;

/**
 * Description of BikiniParser
 *
 * @author guillaume
 */
class DynamoParser extends LinksParser{

    protected $base_url;

    public function __construct(AgendaRepository $repo, $url) {
        parent::__construct($repo, $url);

        $this->base_url = "http://www.ladynamo-toulouse.com";
        return $this;
    }

    /**
     * Retourne les infos d'un agenda depuis une url
     * @return string[]
     */
    protected function getInfosAgenda()
    {
        $tab_retour = [];
        $tab_retour["nom"]              = $this->parser->filter(".t_group")->text();
        $horaires_prix                  = $this->parser->filter(".ticket")->children()->eq(1)->children();
        $tab_retour["horaires"]         = $horaires_prix->eq(0)->text();
        $tab_retour["tarif"]            = preg_replace("/tarif/i","",$horaires_prix->eq(1)->text());
        $tab_retour["date_affichage"]   = "Le ".$this->parser->filter(".tal_date")->text()." à ".$tab_retour["horaires"];
        $tab_retour["lieu_nom"]         = "La Dynamo";
        $tab_retour["reservation_internet"] = implode(" ",$this->parser->filter("#contenu_gauche .boutcss")->each(function($item) { return $item->attr("href"); }));
        $tab_retour["rue"]		= "6 rue Amélie";
        $tab_retour["code_postal"]      = "31000";
        $tab_retour["commune"]          = "TOULOUSE";
        $tab_retour["descriptif"]       = $this->parser->filter(".col_infos")->html();
        $tab_retour["date"]             = "Le ".$this->parser->filter(".tal_date")->text();
        $tab_retour["image"]            = $this->base_url.$this->parser->filter(".tal_img img")->attr("src");

        return $tab_retour;
    }

    public function getLinks()
    {
        $this->parseContent("XML");
        $base_url = $this->base_url;
        return ($this->parser->filter("div.event a")->each(function($item) use($base_url)
        {
            return $base_url.$item->attr("href");
        }));
    }

    public function hydraterAgenda($infos_agenda) {

        $tab_champs = $infos_agenda;

        $date = $this->parseDate($tab_champs["date_affichage"]);

        $dateDebut = \DateTime::createFromFormat("Y-n-d", $date);
        $nom = $tab_champs["nom"];

        $a = $this->getAgendaFromUniqueInfo($nom, $dateDebut);

        $a->setNom($nom);
        $a->setDescriptif($tab_champs["descriptif"]);
        $a->setLieuNom($tab_champs["lieu_nom"]);

        $a->setDateDebut($dateDebut);
        $a->setHoraires("A ".$tab_champs["horaires"]);
        $a->setReservationInternet(preg_replace("/http:\/\//i","",$tab_champs["reservation_internet"]));
        $a->setTarif($tab_champs["tarif"]);

        $a->setRue($tab_champs["rue"]);
        $a->setCodePostal($tab_champs["code_postal"]);
        $a->setCommune($tab_champs["commune"]);
        $a->setVille($tab_champs["commune"]);
        $a->setTypeManifestation("Musique");
        $a->setCategorieManifestation("Concert");
        $a->setThemeManifestation("Musique,");
        $a->setUrl($tab_champs["image"]);

        return $a;
    }

    public function getNomData() {
        return "Dynamo";
    }
}
