<?php
namespace TBN\AgendaBundle\Parser;

use Symfony\Component\DomCrawler\Crawler as Crawler;

class ProgrammeTVParser {
   
    protected $parser;
    
    public function __construct()    
    {
        $this->parser = new Crawler();
        $this->parser->addContent(\file_get_contents("http://www.programme-tv.net/programme/toutes-les-chaines/"), "HTML");
    }
    
    public function getProgrammesTV()
    {
        return $this->parser->filter(".block.programme .channel")->each(function(Crawler $channel)
        {            
            $programmes  = $channel->filter(".programme");        
            if($programmes->count() > 0)
            {
                $programme  = $programmes->eq(0);                
                $episode    = $programme->filter('.prog_episode');
                $logo       = $channel->filter('.channelItem img');
                $chaine     = $channel->filter('.channelItem a.channel_label');
                $heure      = $programme->filter('.prog_heure');
                $nom        = $programme->filter('.prog_name');
                $type       = $programme->filter('.prog_type');
                
                return [
                    "logo"      => $logo->count() ? $logo->attr("src") : null,
                    "chaine"    => $chaine->count() ? str_replace("Programme de ", "", $chaine->attr("title")) : null,
                    "heure"     => $heure->count() ? $heure->text() : null,
                    "nom"       => $nom->count() ? $nom->text() : null,
                    "lien"      => $nom->count() ? "http://www.programme-tv.net/".$nom->attr("href") : null,
                    "type"      => $type->count() ? $type->text() : null,
                    "episode"   => $episode->count() ? $episode->text() : null,
                    "asset"     => null,
                ];
            }
            
            return [
                "logo"      => null,
                "chaine"    => null,
                "heure"     => null,
                "nom"       => null,
                "lien"      => null,
                "type"      => null,
                "episode"   => null,
                "asset"     => null,
            ];
            
            return [
                "logo"      => $channel->filter(".channelItem img")->attr("src"),
                "chaine"    => str_replace("Programme de ", "", $channel->filter(".channelItem a.channel_label")->attr("title")),
                "heure"     => $programme->filter(".prog_heure")->text(),
                "nom"       => $programme->filter(".prog_name")->text(),
                "lien"      => "http://www.programme-tv.net/".$programme->filter(".prog_name")->attr("href"),
                "type"      => $programme->filter(".prog_type")->text(),
                "episode"   => $episode->count() ? $episode->text() : null
            ];
        });
    }
}
