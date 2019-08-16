<?php

namespace App\Parser;

use Symfony\Component\DomCrawler\Crawler as Crawler;

class ProgrammeTVParser
{
    protected $parser;

    public function __construct()
    {
        $this->parser = new Crawler();
    }

    public function getProgrammesTV()
    {
        $this->parser->addContent(\file_get_contents('https://www.programme-tv.net/programme/toutes-les-chaines/'), 'HTML');

        return $this->parser->filter('.bouquet-channelGroup')->slice(0, 2)->filter('.doubleBroadcastCard')->each(function (Crawler $channel) {
            $logo = $channel->filter('.doubleBroadcastCard-channelItem img');
            $chaine = $channel->filter('.doubleBroadcastCard-channelName');
            $heure = $channel->filter('.doubleBroadcastCard-hour');
            $nom = $channel->filter('.doubleBroadcastCard-title');
            $type = $channel->filter('.doubleBroadcastCard-type');

            $labelChaine = trim($chaine->count() ? $chaine->text() : null);
            $css_chaine = $this->getCSSChaine($labelChaine);

            return [
                'logo' => $logo->count() ? \trim(str_replace('30x30', '80x80', $logo->attr('data-src'))) : null,
                'chaine' => $labelChaine,
                'css_chaine' => $css_chaine ? 'icon-' . $css_chaine : null,
                'heure' => $heure->count() ? trim($heure->text()) : null,
                'nom' => $nom->count() ? trim($nom->text()) : null,
                'lien' => $nom->count() ? $nom->attr('href') : null,
                'type' => $type->count() ? trim($type->text()) : null,
            ];
        });
    }

    protected function getCSSChaine($chaine)
    {
        switch ($chaine) {
            case 'TF1':
                return 'tf1';
            case 'France 2':
                return 'france-2';
            case 'France 3':
                return 'france-3';
            case 'Canal+':
            case 'Canal partagé TNT Ile-de-France':
                return 'canalplus';
            case 'Arte':
                return 'arte';
            case 'M6':
                return 'm6';
            case 'France 5':
                return 'france-5';
            case 'C8':
                return 'c8';
            case 'W9':
                return 'w9';
            case 'TMC':
                return 'tmc';
            case 'NT1':
            case 'NT 1':
                return 'nt1';
            case 'NRJ 12':
                return 'nrj-12';
            case 'La Chaîne parlementaire':
            case 'LCP - Public Sénat':
                return 'la-chaine-parlementaire';
            case 'CStar':
            case 'CSTAR':
                return 'cstar';
            case 'France 4':
                return 'france-4';
            case 'BFMTV':
            case 'BFM TV':
                return 'bfmtv';
            case 'iTélé':
            case 'i>Télé':
                return 'itele';
            case 'D17':
                return 'd17';
            case 'Gulli':
                return 'gulli';
            case 'France Ô':
                return 'france-o';
            case 'HD1':
                return 'hd1';
            case "L'Equipe":
                return 'lequipe';
            case 'Franceinfo':
                return 'franceinfo';
            case 'LCI':
            case 'LCI - La Chaîne Info':
                return 'lci-la-chaine-info';
            case '6ter':
                return '6ter';
            case 'Numéro 23':
                return 'numero23';
            case 'RMC Découverte':
                return 'rmc-decouverte';
            case 'Chérie 25':
                return 'cherie-25';
            case 'IDF1':
                return 'idf';
            case 'Canal partagé':
                return 'canal_partage';
            case 'RTL 9':
                return 'rtl9';
            case 'Paris Première':
                return 'paris_premiere';
            case 'Plug RTL':
                return 'plug_rtl';
            case 'TV5 Monde':
            case 'TV5MONDE':
                return 'tv5_monde';
            case '13e Rue':
            case '13e rue':
                return '13_rue';
            case 'E ! Entertainment':
            case 'E !':
                return 'e_entertainment';
            case 'Syfy':
                return 'syfy';
            case 'Série club':
            case 'serieclub':
                return 'serie_club';
            case 'Nat Geo Wild':
                return 'nat_geo';
            case 'TFX':
                return 'tfx';
            case 'CNEWS':
                return 'cnews';
            case 'TF1 Séries Films':
                return 'tf1-series-films';
            case 'RMC Story':
                return 'rmc-story';
        }

        return null;
    }
}
