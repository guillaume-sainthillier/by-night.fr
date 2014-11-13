<?php

namespace TBN\MajDataBundle\Parser;


/**
 * Description of ToulouseParser
 *
 * @author guillaume
 */
class ToulouseParser extends AgendaParser{

    public function parse(\Symfony\Component\Console\Output\OutputInterface $output)
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
        $i = 0;
        while($cursor = fgetcsv($fic,0,';','"','"'))
        {
            $i++;
            $tab = array_map(function($e)
            {
                return iconv('CP1252', 'UTF-8', $e);
            },$cursor);


            if($tab[1] !== "" or $tab[2] !== "")
            {
                $nom = $tab[1] !== "" ? $tab[1] : $tab[2];

                $date_debut = \DateTime::createFromFormat("d/m/Y", $tab[5]);
                $date_fin = \DateTime::createFromFormat("d/m/Y", $tab[6]);

                $a = $this->getAgendaFromUniqueInfo($nom, $date_debut);

                $tab_agendas[] = $a->setNom($nom)
                ->setDescriptif($tab[4])
                ->setDateDebut($date_debut)
                ->setDateFin($date_fin)
                ->setHoraires($tab[7])
                ->setModificationDerniereMinute($tab[9])
                ->setLieuNom($tab[10])
                ->setRue($tab[12])
                ->setCodePostal($tab[14])
                ->setCommune($tab[15])
                ->setVille($tab[15])
                ->setTypeManifestation($tab[16])
                ->setCategorieManifestation($tab[17])
                ->setThemeManifestation($tab[18])
                ->setStationMetroTram($tab[19])
                ->setLatitude($tab[20])
                ->setLongitude($tab[21])
                ->setReservationTelephone($tab[22])
                ->setReservationEmail($tab[23])
                ->setReservationInternet($tab[24])
                ->setManifestationGratuite($tab[25])
                ->setTarif($tab[26])
                ->setTrancheAge($tab[28]);
            }
        }

        return $tab_agendas;
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
        if(($fic = fopen($output_file_zip, "w")) and fwrite($fic, $data))
        {
            fclose($fic);

            $zip = new \ZipArchive();
            if($zip->open($output_file_zip) and $zip->extractTo($output_dir_zip))
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
        return \preg_match("/Agenda So Toulouse/i",$fic) and \is_dir($dir.$fic);
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