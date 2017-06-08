<?php

namespace TBN\MajDataBundle\Parser\Toulouse;

use ForceUTF8\Encoding;
use Symfony\Component\Filesystem\Filesystem;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MajDataBundle\Parser\AgendaParser;

/**
 * Description of ToulouseParser.
 *
 * @author guillaume
 */
class ToulouseParser extends AgendaParser
{
    public function getRawAgendas()
    {
        $fichier = $this->downloadCSV();
        if ($fichier !== null) {
            return $this->parseCSV($fichier);
        }

        return [];
    }

    /**
     * @param string $fichier le chemin absolu vers le fichier
     *
     * @return Agenda[] les agendas parsés
     */
    protected function parseCSV($fichier)
    {
        $tab_agendas = [];

        $fic = fopen($fichier, 'r');
        fgetcsv($fic, 0, ';', '"', '"'); //Ouverture de la première ligne

        while ($cursor = fgetcsv($fic, 0, ';', '"', '"')) {
            $tab = array_map(function ($e) {
                return Encoding::toUTF8($e);
            }, $cursor);

            if ($tab[1] || $tab[2]) {
                $nom = $tab[1] ?: $tab[2];

                $date_debut = new \DateTime($tab[5]);
                $date_fin = new \DateTime($tab[6]);

                $tab_agendas[] = [
                    'nom'                          => $nom,
                    'descriptif'                   => $tab[4],
                    'date_debut'                   => $date_debut,
                    'date_fin'                     => $date_fin,
                    'horaires'                     => $tab[7],
                    'modification_derniere_minute' => $tab[9],
                    'place.nom'                    => $tab[10],
                    'place.rue'                    => $tab[12],
                    'place.latitude'               => $tab[20],
                    'place.longitude'              => $tab[21],
                    'place.code_postal'            => $tab[14],
                    'place.ville'                  => $tab[15],
                    'type_manifestation'           => $tab[16],
                    'categorie_manifestation'      => $tab[17],
                    'theme_manifestation'          => $tab[18],
                    'station_metro_tram'           => $tab[19],
                    'reservation_telephone'        => $tab[22],
                    'reservation_email'            => $tab[23],
                    'reservation_internet'         => $tab[24],
                    'tarif'                        => $tab[26],
                    'source'                       => 'https://data.toulouse-metropole.fr/explore/dataset/agenda-des-manifestations-culturelles-so-toulouse/export/',
                ];
            }
        }

        return $tab_agendas;
    }

    /**
     * Télécharge un fichier CSV sur le repertoire TEMP depuis l'URI de l'Open Data Toulouse.
     *
     * @return string le chemin absolu vers le fichier
     */
    protected function downloadCSV()
    {
        $data = file_get_contents($this->getURL());
        $path_file = sprintf('%s/data_manifestations/agenda.csv', sys_get_temp_dir());
        $fs = new Filesystem();
        $fs->dumpFile($path_file, $data);

        return $path_file;
    }

    public function getNomData()
    {
        return 'Toulouse';
    }
}
