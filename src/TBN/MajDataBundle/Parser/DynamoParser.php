<?php

namespace TBN\MajDataBundle\Parser;

use TBN\AgendaBundle\Repository\AgendaRepository;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Description of BikiniParser
 *
 * @author guillaume
 */
class DynamoParser extends LinksParser{

    protected $base_url;

    protected $currentEvent;

    protected static $URL_PATTERN = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";

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
        $tab_retour["nom"]              = $this->currentEvent->filter("[itemprop='name']")->text();
        $date_debut                     = $this->currentEvent->filter("[itemprop='startDate']")->attr("datetime");
        $date_fin                       = $this->currentEvent->filter("[itemprop='endDate']")->attr("datetime");

        $tab_retour["date_debut"]       = \DateTime::createFromFormat("Y-m-d", $date_debut);
        $tab_retour["date_fin"]         = \DateTime::createFromFormat("Y-m-d", $date_fin);

        //Description
        $tab_retour["descriptif"]       = $this->currentEvent->filter("[itemprop='description']")->html();

        //Image
        $node_image                     = $this->currentEvent->filter(".evo_metarow_fimg");
        $raw_style                      = $node_image->count() ? $node_image->attr("style") : null;
        $image                          = null;
        $matchs_url                     = [];

        if($raw_style && \preg_match(self::$URL_PATTERN, $raw_style, $matchs_url))
        {
            $image                      = str_replace(")", "", $matchs_url[0]);
        }
        $tab_retour["image"]            = $image;


        //Facebook
        $node_facebook                  = $this->currentEvent->filter(".evo_metarow_cusF2 p");
        $raw_facebook                   = $node_facebook->count() ? $node_facebook->text() : null;
        $facebook                       = null;
        $matchs_id                      = [];

        if($raw_facebook && \preg_match("#events/(\d+)#i", $raw_facebook, $matchs_id))
        {
            $facebook                   = $matchs_id[1];            
        }
        $tab_retour["facebook_id"]      = $facebook;

        //Tarifs
        $node_tarifs                    = $this->currentEvent->filter(".evo_metarow_cusF1 p");
        $tarifs                         = [];
        $lien                           = null;
        $liens                          = [];
        
        foreach($node_tarifs as $node)
        {
            $value = str_replace(["Prévente :", "*", "Prévente sur"], "", $node->textContent);

            if(\preg_match(self::$URL_PATTERN, $value, $liens)) //Lien présent
            {
                $lien = $liens[0];
                $value = str_replace($lien, "", $value);
            }
            $tarifs[] = trim($value);
        }

        $tab_retour["tarif"]            = implode(", ", $tarifs);
        $tab_retour["reservation_internet"] = $lien;

        
        //Horaires
        $node_horaires                  = $this->currentEvent->filter(".evo_metarow_time p");
        $raw_horaires                   = $node_horaires->count() ? $node_horaires->text() : null;
        $horaires                       = null;

        if($raw_horaires)
        {
            $value                      = preg_replace("/.*\(.+\)\s?/i", "", $raw_horaires);
            $value                      = preg_replace("/[^\dh\-]/i", "", $value);
            $values                     = explode("-", $value);
            if(count($values) <= 1)
            {
                $horaires = "A ".$values[0];
            }  else
            {
                $horaires = "De ".$values[0]." à ".$values[1];
            }
        }
        $tab_retour["horaires"]         = $horaires;

        //Catégorie & Thèmes
        $themes                         = implode(",", $this->currentEvent
                                            ->filter(".evcal_desc3 .evcal_event_types em")
                                            ->reduce(function(Crawler $node, $i) {
                                                return $i > 0;
                                            })->each(function($node)
                                            {
                                                return $node->text();
                                            }));
        $tab_retour["theme"]            = $themes;

        $categorie                      = $this->currentEvent->filter(".evcal_event_subtitle");
        $tab_retour["categorie"]        = $categorie->count()?  $categorie->text() : null;

        //Lieux
        $tab_retour["lieu_nom"]         = "La Dynamo";        
        $tab_retour["rue"]		= "6 rue Amélie";
        $tab_retour["code_postal"]      = "31000";
        $tab_retour["commune"]          = "TOULOUSE";

        //Source
        $nodeSource                     = $this->currentEvent->filter(".evo_event_schema a[itemprop='url']");
        $tab_retour["source"]           = $nodeSource->count() ? $nodeSource->attr("href") : null;

        return $tab_retour;
    }

    public function parse(\Symfony\Component\Console\Output\OutputInterface $output)
    {
        $this->parseContent();

        return $this->parser->filter(".eventon_list_event")->each(function($node, $i)
        {
            $this->currentEvent = $node;
            $infos_agenda = $this->getInfosAgenda(); //Récupère les infos de l'agenda depuis le lien
            return $this->hydraterAgenda($infos_agenda); //Créé ou récupère l'agenda associé aux infos
        });
    }

    public function hydraterAgenda($infos_agenda) {
        $tab_champs = $infos_agenda;
        
        $dateDebut  = $tab_champs["date_debut"];
        $nom        = $tab_champs["nom"];

        $a = $this->getAgendaFromUniqueInfo($nom, $dateDebut, null, $tab_champs["lieu_nom"]);
        $a->setNom($nom);
        $a->setDescriptif($tab_champs["descriptif"]);
        $a->setLieuNom($tab_champs["lieu_nom"]);
        $a->setUrl($tab_champs["image"]);
        $a->setFacebookEventId($tab_champs["facebook_id"]);

        $a->setDateDebut($dateDebut);
        $a->setDateFin($tab_champs["date_fin"]);
        $a->setHoraires($tab_champs["horaires"]);
        $a->setReservationInternet(preg_replace("/http:\/\//i","",$tab_champs["reservation_internet"]));
        $a->setTarif($tab_champs["tarif"]);

        $a->setRue($tab_champs["rue"]);
        $a->setCodePostal($tab_champs["code_postal"]);
        $a->setCommune($tab_champs["commune"]);
        $a->setVille($tab_champs["commune"]);

        $a->setTypeManifestation("Concert,");
        $a->setCategorieManifestation($tab_champs["categorie"]);
        $a->setThemeManifestation($tab_champs["theme"]);
        $a->setSource($tab_champs["source"]);

        return $a;
    }

    public function getNomData() {
        return "Dynamo";
    }

    public function getLinks() {
        return [];
    }
}
