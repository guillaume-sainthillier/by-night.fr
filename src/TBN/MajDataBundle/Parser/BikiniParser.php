<?php

namespace TBN\MajDataBundle\Parser;

/**
 *
 * @author Guillaume SAINTHILLIER
 */
class BikiniParser extends LinksParser{

    /**
     * Retourne les infos d'un agenda depuis une url
     * @return string[]
     */
    protected function getInfosAgenda()
    {
        $tab_retour = [];

        $tab_retour["reservation_internet"]         = implode(" ",$this->parser->filter("#reservation a.boutonReserverSpectacle")->each(function($item) { return $item->attr("href"); }));
        $tab_retour["date_affichage"]               = $this->parser->filter("#date")->text();
        $tab_retour["nom"]                          = $this->parser->filter("#blocContenu h2")->text();
        $tab_retour["lieu_nom"]                     = $this->parser->filter("#salle h3")->text();
        $tab_retour["adresse"]                      = $this->parser->filter("#salle #adresse")->html();
        $tab_retour["descriptif"]                   = $this->parser->filter("#texte")->html();
        $tab_retour["tarif"]                        = "";
        $tab_retour["theme"]                        = "";
        $tab_retour["image"]                        = $this->parser->filter("#blocImage a[rel=shadowbox]")->attr("href");
        $tab_retour["source"]                       = $this->url;


        $this->parser->filter("#blocContenu")->children()->each(function($sibling) use(&$tab_retour)
        {
            if($sibling->attr("id") === "prix")
            {
                $tab_retour["tarif"] = trim($sibling->text());
                return $sibling;
            }
            return false;
        });
        $this->parser->filter("#blocContenu")->children()->each(function($sibling) use(&$tab_retour)
        {
            if($sibling->attr("id") === "type")
            {
                $tab_retour["theme"] = preg_replace("/style\s?:\s?/i", "", trim($sibling->text()));
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
        $this->parseContent("XML");
        return $this->parser->filter("item")->each(function($item)
        {
            return trim(preg_replace("/(.*)<link>(.*)<description>(.*)/im","$2",preg_replace("/\n/","",$item->html())));
        });
    }

    /**
     *
     * @param array $infos_agenda
     * @return Agenda l'agenda créé ou hydraté
     */
    public function hydraterAgenda($infos_agenda) {

        $tab_champs = $infos_agenda;

        $date = $this->parseDate($tab_champs["date_affichage"]);
        $dateDebut = \DateTime::createFromFormat("Y-n-d", $date);
        $nom = $tab_champs["nom"];

        $a = $this->getAgendaFromUniqueInfo($nom, $dateDebut, null, $tab_champs["lieu_nom"]);

        $a->setNom($nom);
        $a->setDescriptif(html_entity_decode($tab_champs["descriptif"]));
        $a->setLieuNom($tab_champs["lieu_nom"]);

        $a->setDateDebut($dateDebut);
        $a->setHoraires(preg_replace("/^(.+)à (\d{2}):(\d{2})$/i","A $2h$3.",$tab_champs["date_affichage"]));
        $a->setReservationInternet(preg_replace("/http:\/\//i","",$tab_champs["reservation_internet"]));
        $a->setTarif($tab_champs["tarif"]);

	/*
	 *  Rond point Madame de Mondonville Boulevard Netwiller
	    TOULOUSE
	 */
        $full_adresse = preg_split("/<br\/?>/i",$tab_champs["adresse"]);
	$ville = $full_adresse[1];
	if(preg_match("/\d/i", $ville))
	{
	    $a->setCodePostal(preg_replace("/\D/i", "", $ville));
	    $ville = preg_replace("/\d/i", "", $ville);
	}

        $a->setRue($full_adresse[0]);
        $a->setCommune(strtoupper($ville));
        $a->setVille(strtoupper($ville));
        $a->setTypeManifestation("Musique");
        $a->setCategorieManifestation("Concert");
        $a->setThemeManifestation($tab_champs["theme"]);
        $a->setUrl($tab_champs["image"]);
        $a->setSource($tab_champs["source"]);

        return $a;
    }

    public function getNomData() {
        return "Bikini";
    }
}
