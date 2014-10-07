<?php

namespace TBN\MajDataBundle\Parser;

use TBN\AgendaBundle\Repository\AgendaRepository;

/**
 * Description of BikiniParser
 *
 * @author guillaume
 */
class ParisTourismeParser extends LinksParser {

    protected $base_url;

    public function __construct(AgendaRepository $repo) {

        $now = new \DateTime;
        $now2 = new \DateTime;
        $now2->modify("+1 year");
        $search_params = [
            "search_type" => "agenda",
            "nbPPage" => 100,
            "filter[]" => "attr_begin_dt:".$now->format("Y-m-d")."&filter[]=attr_end_dt:".$now2->format("Y-m-d"),
        ];

        parent::__construct($repo, "http://www.parisinfo.com/ou-sortir-a-paris/infos/rechercher-une-sortie?".  urldecode(http_build_query($search_params)));
        $this->base_url = "http://www.parisinfo.com/";
    }

    /**
     * Retourne les infos d'un agenda depuis une url
     * @return string[]
     */
    protected function getInfosAgenda() {
        $tab_retour = [];

        //Dates
        $node_date  = $this->parser->filter(".ficheDetailBlocTexte .date_evenement");
        $dates      = trim($node_date->text());
        $date_debut = $this->parseDate(preg_replace("/du (.+) au(.+)/i", "$1", $dates));
        $date_fin   = $this->parseDate(preg_replace("/du (.+) au(.+)/i", "$2", $dates));

        //Résérvations Internet, Téléphone & Mails
        $node_resa_mail     = $this->parser->filter(".ficheContact");
        $resa_mail          = $node_resa_mail->count() ? str_replace("mailto:", "",$node_resa_mail->attr("href")) : null;

        $nodes_contact      = $this->parser->filter(".ongletsBlocContenuFicheColGauche p");
        $node_resa_internet = $nodes_contact->reduce(function($p)
        {
            return preg_match("/Site Internet/i", $p->text()) === 1;
        });
        $node_resa_telephone = $nodes_contact->reduce(function($p)
        {
            return preg_match("/Téléphone/iu", $p->text()) === 1;
        });

	$resa_telephone	= null;
	if($node_resa_telephone->count())
	{
	    $tel_fax = explode("-", $node_resa_telephone->text());
	    // Téléphone :  - Fax :
	    $resa_telephone = trim(str_replace("Téléphone :", "", $tel_fax[0]));
	}

        $resa_internet  = $node_resa_internet->count() ? $node_resa_internet->filter("a")->attr("href") : null;

        //Description complète
        $description = trim($this->parser->filter(".ContenuFicheDescriptif .ficheDescription")->eq(1)->html());


        //Lieux
	$lieux	    = $this->parser->filter(".ficheDetailDroit p");
        $adresses   = $this->parser->filter(".ficheDetailDroit .detailAdresse");
        $rue        = null;
        $cp         = null;
        $ville      = null;

	$nom_full_lieu = $lieux->reduce(function($p)
	{
	    return !preg_match("/detailAdresse/i", $p->attr("class"));
	});

	$nom_lieu   = $nom_full_lieu->count() ? $nom_full_lieu->text() : null;
	$lieu	    = null;
	if($nom_lieu)
	{
	    //Versailles, 78 - Yvelines
	    $lieux = explode(",", $nom_lieu);
	    $lieu  = $lieux[0];
	}

        if($adresses->count() >= 2)
        {
            $rue        = $adresses->eq(0)->text();
            $cp_ville   = $adresses->eq(1)->text();

            //31500 Toulouse -> 31500
            $cp         = preg_replace("/^(\d+)(.+)/i", "$1", $cp_ville);
            //31500 Toulouse -> Toulouse
            $ville      = preg_replace("/^(\d+)(.+)/i", "$2", $cp_ville);
        }

        $nom    = $this->parser->filter(".ficheDetailGauche .cartoucheTitre, .ficheDetailGauche .cartoucheTitreCommercial")->text();
        $image  = str_replace("140x140", "350x350", $this->parser->filter(".ficheDetailGauche img")->eq(0)->attr("src"));
        $themes = $this->parser->filter(".ContenuFicheDescriptif .ficheDescription")->eq(0)->text();

        //Vérification présence image en meilleure qualité
        $ch = curl_init($image);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($retcode !== 200)
        {
            $image = str_replace("350x350", "140x140", $image);
        }

        $themes_categories = explode(":", $themes);

        $theme = null;
        $categorie = null;

        if(count($themes_categories) == 2)
        {
            $theme = $themes_categories[0];
            $categories = explode("-", $themes_categories[1]);
            $categorie = array_pop($categories);
        }

        $tab_retour["date_debut"]           = \DateTime::createFromFormat("Y-m-d", $date_debut);
        $tab_retour["date_fin"]             = \DateTime::createFromFormat("Y-m-d", $date_fin);
        $tab_retour["image"]                = $image;
        $tab_retour["nom"]                  = $nom;
        $tab_retour["lieu"]                 = $lieu;
        $tab_retour["theme"]                = $theme;
        $tab_retour["categorie"]            = $categorie;
        $tab_retour["rue"]                  = $rue;
        $tab_retour["code_postal"]          = $cp;
        $tab_retour["ville"]                = $ville;
        $tab_retour["commune"]              = $ville;
        $tab_retour["description"]          = $description;
        $tab_retour["reservation_telephone"]= $resa_telephone;
        $tab_retour["reservation_internet"] = $resa_internet;
        $tab_retour["reservation_mail"]     = $resa_mail;

        return $tab_retour;
    }

    public function getLinks() {
        $this->parseContent("HTML");

        $urls = [];
        while ($this->url !== null) {
            $events = $this->parser->filter(".ResultatRechercheColDroite .cartoucheListe");

            $urls = array_merge($urls, $events->each(function($item) {
                return $item->filter(".cartoucheTitre a, .cartoucheTitreCommercial a")->attr("href");
            }));

            $this->url = null;
            $next = $this->parser->filter(".resultatNavigation a.resultatSuivant");
            if ($next->count() > 0) {
                $this->url = $this->base_url . $next->eq(0)->attr("href");
                $this->parseContent();
            } else {
                $this->url = null;
            }
        }

        return $urls;
    }

    public function hydraterAgenda($infos_agenda) {

        $tab_champs = $infos_agenda;

        $dateDebut  = $tab_champs["date_debut"];
        $nom        = $tab_champs["nom"];
        $a          = $this->getAgendaFromUniqueInfo($nom, $dateDebut);

        $a->setDateFin($tab_champs["date_fin"]);
        $a->setDateDebut($dateDebut);
        $a->setUrl($tab_champs["image"]);
        $a->setNom($nom);
        $a->setLieuNom($tab_champs["lieu"]);
        $a->setThemeManifestation($tab_champs["theme"]);
        $a->setCategorieManifestation($tab_champs["categorie"]);
        $a->setRue($tab_champs["rue"]);
        $a->setCodePostal($tab_champs["code_postal"]);
        $a->setVille($tab_champs["ville"]);
        $a->setCommune($tab_champs["commune"]);
        $a->setDescriptif($tab_champs["description"]);
        $a->setReservationTelephone($tab_champs["reservation_telephone"]);
        $a->setReservationInternet($tab_champs["reservation_internet"]);
        $a->setReservationEmail($tab_champs["reservation_mail"]);

        return $a;
    }

    public function getNomData() {
        return "ParisTourisme";
    }
}