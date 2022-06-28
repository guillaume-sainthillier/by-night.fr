<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Toulouse;

use App\Parser\AbstractParser;
use DateTime;
use ForceUTF8\Encoding;
use Symfony\Component\Filesystem\Filesystem;

class ToulouseParser extends AbstractParser
{
    private const DOWNLOAD_URL = 'https://data.toulouse-metropole.fr/explore/dataset/agenda-des-manifestations-culturelles-so-toulouse/download/?format=csv&timezone=Europe/Berlin&use_labels_for_header=true';

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'Toulouse';
    }

    /**
     * {@inheritDoc}
     */
    public function parse(bool $incremental): void
    {
        $fichier = $this->downloadCSV();

        if (null !== $fichier) {
            $this->parseCSV($fichier);
        }
    }

    /**
     * Télécharge un fichier CSV sur le repertoire TEMP depuis l'URI de l'Open Data Toulouse.
     *
     * @return string le chemin absolu vers le fichier
     */
    private function downloadCSV()
    {
        $data = file_get_contents(self::DOWNLOAD_URL);
        $path_file = sprintf('%s/data_manifestations/agenda.csv', sys_get_temp_dir());
        $fs = new Filesystem();
        $fs->dumpFile($path_file, $data);

        return $path_file;
    }

    private function parseCSV(string $fichier): void
    {
        $fic = fopen($fichier, 'r');
        fgetcsv($fic, 0, ';', '"', '"'); //Ouverture de la première ligne

        while ($cursor = fgetcsv($fic, 0, ';', '"', '"')) {
            $tab = array_map(fn ($e) => Encoding::toUTF8($e), $cursor);

            if (!$tab[1] && !$tab[2]) {
                continue;
            }

            $nom = $tab[1] ?: $tab[2];

            $date_debut = new DateTime($tab[5]);
            $date_fin = new DateTime($tab[6]);

            $event = [
                'external_id' => 'TOU-' . $tab[0],
                'nom' => $nom,
                'descriptif' => $tab[4],
                'date_debut' => $date_debut,
                'date_fin' => $date_fin,
                'horaires' => $tab[7],
                'modification_derniere_minute' => $tab[9],
                'placeName' => $tab[10],
                'placeStreet' => $tab[12],
                'latitude' => (float) $tab[20] ?: null,
                'longitude' => (float) $tab[21] ?: null,
                'placePostalCode' => $tab[14],
                'placeCity' => $tab[15],
                'placeCountryName' => 'France',
                'type_manifestation' => $tab[16],
                'categorie_manifestation' => $tab[17],
                'theme_manifestation' => $tab[18],
                'phoneContacts' => [$tab[22]] ?: null,
                'mailContacts' => [$tab[23]] ?: null,
                'websiteContacts' => [$tab[24]] ?: null,
                'tarif' => $tab[26],
                'source' => 'https://data.toulouse-metropole.fr/explore/dataset/agenda-des-manifestations-culturelles-so-toulouse/information/',
            ];

            $this->publish($event);
        }
        fclose($fic);
    }
}
