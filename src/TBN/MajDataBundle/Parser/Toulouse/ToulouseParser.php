<?php

namespace TBN\MajDataBundle\Parser\Toulouse;

use TBN\MajDataBundle\Parser\AgendaParser;

/**
 * Description of ToulouseParser
 *
 * @author guillaume
 */
class ToulouseParser extends AgendaParser {

    public function getRawAgendas()
    {
        $fichier = $this->downloadCSV();
        if($fichier !== null)
        {
            return $this->parseCSV($fichier);
        }

        return [];
    }

    /**
     *
     * @param string $fichier le chemin absolu vers le fichier
     * @return Agenda[] les agendas parsés
     */
    protected function parseCSV($fichier)
    {
        $tab_agendas = [];

        $fic = fopen($fichier,"r");
        $cursor = fgetcsv($fic,0,';','"','"'); //Ouverture de la première ligne
        
        while($cursor = fgetcsv($fic,0,';','"','"'))
        {
            $tab = array_map(function($e)
            {
                return iconv('CP1252', 'UTF-8', $e);
            },$cursor);


            if($tab[1] !== "" || $tab[2] !== "")
            {
                $nom = $tab[1] !== "" ? $tab[1] : $tab[2];

                $date_debut = \DateTime::createFromFormat("d/m/Y", $tab[5]);
                $date_fin = \DateTime::createFromFormat("d/m/Y", $tab[6]);

                $tab_agendas[] = [
                    'nom' => $nom,
                    'descriptif' => $tab[4],
                    'date_debut' => $date_debut,
                    'date_fin' => $date_fin,
                    'horaires' => $tab[7],
                    'modification_derniere_minute' => $tab[9],
                    'place.nom' => $tab[10],
                    'place.rue' => $tab[12],
                    'place.latitude' => $tab[20],
                    'place.longitude' => $tab[21],
                    'place.code_postal' => $tab[14],
                    'place.ville' => $tab[15],
                    'type_manifestation' => $tab[16],
                    'categorie_manifestation' => $tab[17],
                    'theme_manifestation' => $tab[18],
                    'station_metro_tram' => $tab[19],                    
                    'reservation_telephone' => $tab[22],
                    'reservation_email' => $tab[23],
                    'reservation_internet' => $tab[24],
                    'manifestation_gratuite' => $tab[25],
                    'tarif' => $tab[26],
                    'source' => 'http://data.toulouse-metropole.fr/web/guest/les-donnees/-/opendata/card/21905-agenda-des-manifestations-culturelles/'
                ];
            }
        }

        return $tab_agendas;
    }

    protected function getURL()
    {
        return "http://data.grandtoulouse.fr/web/guest/les-donnees/-/opendata/card/21905-agenda-des-manifestations-culturelles/resource/document?p_p_state=exclusive&_5_WAR_opendataportlet_jspPage=%2Fsearch%2Fview_card_license.jsp";
    }

    /**
     * Télécharge un fichier CSV sur le repertoire TEMP depuis l'URI de l'Open Data Toulouse
     * @return string le chemin absolu vers le fichier
     */
    protected function downloadCSV()
    {
        $data = file_get_contents($this->getURL());

        $output_dir = sys_get_temp_dir()."/";
        $output_file = "data_manifestations";
        $output_dir_zip = $output_dir.$output_file."/";
        $output_file_zip = $output_dir.$output_file.".zip";

        $fic = false;
        if(($fic = fopen($output_file_zip, "w")) && fwrite($fic, $data))
        {
            fclose($fic);

            $zip = new \ZipArchive();
            if($zip->open($output_file_zip) && $zip->extractTo($output_dir_zip))
            {
                foreach(scandir($output_dir_zip) as $fichier)
                {
                    if($this->isGoodDataFolder($fichier, $output_dir_zip))
                    {
                        foreach(scandir($output_dir_zip.$fichier) as $csv)
                        {
                            if($this->isGoodDataFile($csv))
                            {
                                return $output_dir_zip.$fichier."/".$csv;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Retoune la validation du fichier ZIP passé en paramètre
     * @param string $fic le nom du fichier
     * @param string $dir le chemin vers le repertoire du fichier
     * @return boolean vrai si le nom du fichier ZIP est correct, faux sinon
     */
    protected function isGoodDataFolder($fic, $dir)
    {
        return \preg_match("/Agenda So Toulouse/i",$fic) && \is_dir($dir.$fic);
    }

    /**
     * Retourne le test d'une extension .CSV
     * @param string $fichier le nom du fichier
     * @return boolean vrai si le fichier est un fichier CSV, faux sinon
     */
    protected function isGoodDataFile($fichier)
    {
        return \preg_match("/\.csv$/i",$fichier);
    }

    public function getNomData() {
        return "Toulouse";
    }
}
