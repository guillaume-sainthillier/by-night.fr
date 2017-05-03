<?php

namespace TBN\MajDataBundle\Parser\Common;

use Symfony\Component\DomCrawler\Crawler;
use TBN\MajDataBundle\Parser\LinksParser;
use \ForceUTF8\Encoding;

/**
 * Description of SoonNightParser
 *
 * @author guillaume
 */
class SoonNightParser extends LinksParser
{

    public function __construct()
    {
        parent::__construct();

        $this->setBaseUrl('http://www.soonnight.com');
    }

    /**
     * Retourne les infos d'un agenda depuis une url
     * @return string[]
     */
    protected function getInfosAgenda()
    {
        $tab_retour = [];

        //Date & Nom
        $date_lieu = preg_split("/-/", $this->parser->filter(".soiree_fiche h2.sous_titre")->text());
        $nom = preg_replace("/\&(\d+);/i", "&#$1;", $this->parser->filter("h1.titre_principal")->text()) . " @ " . $date_lieu[1];
        $tab_retour['nom'] = $this->decodeNumCharacter($nom);
        $date = $this->parseDate($date_lieu[0]);

        $tab_retour['date_debut'] = \DateTime::createFromFormat('Y-n-d', $date);

        //Lieux
        $rue = null;
        $code_postal = null;
        $lieu = null;
        $lat = null;
        $long = null;
        $ville = null;
        $lieux = $this->parser->filter('.adresse');
        if ($lieux->count()) {
            $infosGPS = array_map('trim', explode(',', $lieux->attr('longlat')));
            if(count($infosGPS) > 1) {
                $lat = $infosGPS[0];
                $long = $infosGPS[1];
            }

            $adresse = $lieux->text();
            list($rue, $code_postal, $ville) = $this->normalizeAddress($adresse);

            $node_lieu = $this->parser->filter('.titre.nom_lieu');
            $lieu = $node_lieu->count() ? trim($node_lieu->text()) : null;
        }
        $tab_retour['place.latitude'] = $lat;
        $tab_retour['place.longitude'] = $long;
        $tab_retour['place.nom'] = $lieu;
        $tab_retour['place.rue'] = $rue;
        $tab_retour['place.code_postal'] = $code_postal;
        $tab_retour['place.ville'] = $ville;


        //Tarifs
        $node_tarif = $this->getSibling($this->parser->filter('.row_bloc .icon-euro'), '.texte');
        $tab_retour['tarif'] = $node_tarif ? trim($node_tarif->text()) : null;

        //Description
        $descriptif_long = $this->parser->filter(".description_soiree");
        $descriptif = null;

        //Suppression des foutues pubs
        foreach ($descriptif_long as $node) {
            foreach ($node->childNodes as $children) {
                if ($children->nodeType === XML_TEXT_NODE || !in_array($children->nodeName, ['span', 'div'])) {
                    $descriptif .= ($children->nodeName === 'br' ? '<br>' : $children->textContent . ' ');
                }
            }
        }

        $black_list = [
            "Toute l'équipe répond à vos questions au ",
            "Réservation et complément d'information au ",
            "réservation simple et rapide au ",
            "Appelez le   *",
            "Mise en relation",
            "Afficher le numéro du service de mise en relation"
        ];
        $clean_descriptif = $this->decodeNumCharacter(str_replace($black_list, "", $descriptif));
        $tab_retour['descriptif'] = $clean_descriptif;

        //Catégorie & Thème
        $node_categorie = $this->getNodeFromHeading($this->parser->filter('.fiche_soiree_top .info .type'));
        $tab_retour['categorie_manifestation'] = $node_categorie ? trim($node_categorie->text()) : null;
        $tab_retour['categorie_manifestation'] = strstr($tab_retour['categorie_manifestation'], 'After Work') !== false ? 'After Work' : $tab_retour['categorie_manifestation'];

        $node_musique = $this->getSibling($this->parser->filter('.icon-music'), '.texte');
        $tab_retour['theme_manifestation'] = $node_musique ? trim($node_musique->text()) : null;
        $tab_retour['type_manifestation'] = 'Soirée';

        //Image
        $image = $this->parser->filter('.fiche_soiree_top .image');
        $tab_retour['url'] = $image->count() ? $image->attr('data-src') : null;
        $tab_retour['source'] = $this->url;

        return $tab_retour;
    }

    public function normalizeAddress($adresse) {
        $infos = array_values(array_filter(array_map('trim', explode(',', $adresse))));

        $rue = null;
        $code_postal = null;
        $ville = null;

        /*
         * 46 rue des lombards, 75001 Paris
         * 46, rue des lombards, 75001 Paris
         * Canal de l'Ourcq - Parc de la Villette, 59 Boulevard Macdonald, 75019 Paris
         * Escale de Passy, parking Passy face à la maison de la radio, 75016 Paris
         */
        if(count($infos) > 2) {
            if(is_numeric($infos[0])) {
                $infos[0] = sprintf(
                    "%s %s",
                    $infos[0],
                    $infos[1]
                );
                $infos[1] = $infos[2];
            }elseif(! preg_match("#^\d{5} #", $infos[1])) {
                $infos[0] = $infos[1];
                $infos[1] = $infos[2];
            }
            unset($infos[2]);
        }

        $infosRue = array_values(array_filter(array_map('trim', explode('-', $infos[0]))));
        $rue = $infosRue[count($infosRue) - 1];
        if(count($infos) > 1) {
            $infosVille = array_map('trim', explode(' ', $infos[1]));
            $code_postal = $infosVille[0];
            if(count($infosVille) > 0) {
                $ville = implode(' ', array_slice($infosVille, 1, count($infosVille)));
            }
        }

        return [$rue, $code_postal, $ville];
    }

    public function getLinks()
    {
        $urls = [];
        foreach($this->urls as $url) {
            $this->setURL($url);
            $this->parseContent('HTML');
            $currentUrls = $this->parser->filter('div.soiree_liste .col_left a.titre')->each(function (Crawler $item) {
                return $this->base_url . $item->attr('href');
            });

            $urls = array_merge($urls, $currentUrls);
        }

        return $urls;
    }

    public function getNomData()
    {
        return 'SoonNight';
    }

    protected function parseDate($date)
    {
        $tabMois = ['janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];

        return preg_replace_callback("/(.+)(\d{2}) (" . implode("|", $tabMois) . ") (\d{4})(.+)/iu",
            function ($items) use ($tabMois) {
                return $items[4] . "-" . (array_search(strtolower($items[3]), $tabMois) + 1) . "-" . $items[2];
            }, $date);
    }

    protected function decodeNumCharacter($t)
    {
        return Encoding::UTF8FixWin1252Chars(Encoding::fixUTF8($t));
    }

    protected function getSibling(Crawler $node, $filter) {
        if($node->count()) {
            $parents = $node->parents();
            if($parents->count()) {
                $sibling = $parents->filter($filter);
                if($sibling->count()) {
                    return $sibling->eq(0);
                }
            }
        }

        return null;
    }

    protected function getNodeFromHeading(Crawler $heading)
    {
        $node = null;

        if ($heading->count()) {
            $parents = $heading->eq(($heading->count() - 1))->parents();
            if ($parents->count()) {
                $siblings = $parents->eq(0)->siblings();
                if ($siblings->count()) {
                    return $siblings->eq(0);
                }
            }
        }

        return $node;
    }
}
