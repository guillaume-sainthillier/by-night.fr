<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Toulouse;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\Parser\AbstractParser;
use DateTimeImmutable;
use ForceUTF8\Encoding;
use Symfony\Component\Filesystem\Filesystem;

final class ToulouseParser extends AbstractParser
{
    private const string DOWNLOAD_URL = 'https://data.toulouse-metropole.fr/explore/dataset/agenda-des-manifestations-culturelles-so-toulouse/download/?format=csv&timezone=Europe/Berlin&use_labels_for_header=true';

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

        $this->parseCSV($fichier);
    }

    /**
     * Télécharge un fichier CSV sur le repertoire TEMP depuis l'URI de l'Open Data Toulouse.
     *
     * @return string le chemin absolu vers le fichier
     */
    private function downloadCSV(): string
    {
        $data = file_get_contents(self::DOWNLOAD_URL);
        $path_file = \sprintf('%s/data_manifestations/agenda.csv', sys_get_temp_dir());
        $fs = new Filesystem();
        $fs->dumpFile($path_file, $data);

        return $path_file;
    }

    private function parseCSV(string $fichier): void
    {
        $fic = fopen($fichier, 'r');
        fgetcsv($fic, 0, ';', '"', '"'); // Ouverture de la première ligne

        while ($cursor = fgetcsv($fic, 0, ';', '"', '"')) {
            $tab = array_map(Encoding::toUTF8(...), $cursor);

            if (!$tab[1] && !$tab[2]) {
                continue;
            }

            $nom = $tab[1] ?: $tab[2];

            $startDate = new DateTimeImmutable($tab[5]);
            $endDate = new DateTimeImmutable($tab[6]);

            $event = new EventDto();
            $event->externalId = $tab[0];

            $event->name = $nom;
            $event->fromData = self::getParserName();
            $event->description = $tab[4];
            $event->startDate = $startDate;
            $event->endDate = $endDate;
            $event->hours = implode('.', array_unique(explode('.', (string) $tab[7])));
            $event->status = $tab[9];
            $event->latitude = (float) $tab[20];
            $event->longitude = (float) $tab[21];
            $event->type = $tab[16];
            $event->category = $tab[17];
            $event->theme = $tab[18];
            $event->phoneContacts = [$tab[22]];
            $event->emailContacts = [$tab[23]];
            $event->websiteContacts = [$tab[24]];
            $event->prices = $tab[26];
            $event->source = 'https://data.toulouse-metropole.fr/explore/dataset/agenda-des-manifestations-culturelles-so-toulouse/information/';

            $place = new PlaceDto();
            $place->externalId = sha1(mb_strtolower(\sprintf(
                '%s %s %s %s',
                $tab[10],
                $tab[12],
                $tab[14],
                $tab[15],
            )));
            $place->name = $tab[10];
            $place->street = $tab[12];

            $city = new CityDto();
            $city->postalCode = $tab[14];
            $city->name = $tab[15];

            $country = new CountryDto();
            $country->code = 'FR';

            $city->country = $country;

            $place->city = $city;
            $place->country = $country;

            $event->place = $place;

            $this->publish($event);
        }

        fclose($fic);
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandName(): string
    {
        return 'toulouse.opendata';
    }
}
