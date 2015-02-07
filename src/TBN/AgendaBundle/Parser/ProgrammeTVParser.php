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
            $programme  = $channel->filter(".programme")->eq(0);
            $episode    = $programme->filter(".prog_episode");
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
