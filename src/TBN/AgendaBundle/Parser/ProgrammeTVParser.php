<?php

namespace TBN\AgendaBundle\Parser;

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
        $this->parser->addContent(\file_get_contents('http://www.programme-tv.net/programme/toutes-les-chaines/'), 'HTML');

        return $this->parser->filter('.p-v-md')->each(function (Crawler $channel) {
            $programmes = $channel->filter('.programme');
            if ($programmes->count() > 0) {
                $programme = $programmes->eq(0);
                $episode = $programme->filter('.prog_episode');
                $logo = $channel->filter('.channel_logo img');
                $chaine = $channel->filter('a.channel_label');
                $heure = $programme->filter('.prog_heure');
                $nom = $programme->filter('.prog_name');
                $type = $programme->filter('.prog_type');

                $labelChaine = $chaine->count() ? str_replace('Programme de ', '', $chaine->attr('title')) : null;
                $css_chaine = $this->getCSSChaine($labelChaine);

                return [
                    'logo'       => $logo->count() ? trim($logo->attr('data-src')) : null,
                    'chaine'     => $labelChaine,
                    'css_chaine' => $css_chaine ? 'icon-'.$css_chaine : null,
                    'heure'      => $heure->count() ? $heure->text() : null,
                    'nom'        => $nom->count() ? $nom->text() : null,
                    'lien'       => $nom->count() ? 'http://www.programme-tv.net/'.$nom->attr('href') : null,
                    'type'       => $type->count() ? $type->text() : null,
                    'episode'    => $episode->count() ? $episode->text() : null,
                    'asset'      => null,
                ];
            }

            return [
                'logo'    => null,
                'chaine'  => null,
                'heure'   => null,
                'nom'     => null,
                'lien'    => null,
                'type'    => null,
                'episode' => null,
                'asset'   => null,
            ];
        });
    }

    protected function getCSSChaine($chaine)
    {
        switch ($chaine) {
            case 'TF1':
                return 'tf1';
            case 'France 2':
                return 'france2';
            case 'France 3':
                return 'france3';
            case 'Canal+':
            case 'Canal partagé TNT Ile-de-France':
                return 'canal_plus';
            case 'Arte':
                return 'arte';
            case 'M6':
                return 'm6';
            case 'France 5':
                return 'france5';
            case 'C8':
                return 'canal8';
            case 'W9':
                return 'w9';
            case 'TMC':
                return 'tmc';
            case 'NT1':
            case 'NT 1':
                return 'nt1';
            case 'NRJ 12':
                return 'nrj';
            case 'La Chaîne parlementaire':
            case 'LCP - Public Sénat':
                return 'lcp';
            case 'CStar':
            case 'CSTAR':
                return 'cstar';
            case 'France 4':
                return 'france4';
            case 'BFM TV':
                return 'bfm_tv';
            case 'iTélé':
            case 'i>Télé':
                return 'itele';
            case 'D17':
                return 'd17';
            case 'Gulli':
                return 'gulli';
            case 'France Ô':
                return 'franceo';
            case 'HD1':
                return 'hd1';
            case "L'Equipe":
                return 'lequipe';
            case 'Franceinfo':
                return 'franceinfo';
            case 'LCI':
            case 'LCI - La Chaîne Info':
                return 'lci';
            case '6ter':
                return '6ter';
            case 'Numéro 23':
                return 'numero23';
            case 'RMC Découverte':
                return 'rmc';
            case 'Chérie 25':
                return 'cherie25';
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
        }
    }
}
