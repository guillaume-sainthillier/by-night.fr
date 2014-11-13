<?php

namespace TBN\MajDataBundle\Parser;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Description of RSSParser
 *
 * @author Guillaume S. <guillaume@sainthillier.fr>
 */
abstract class LinksParser extends AgendaParser {
    
    /*
     * @var $parser Symfony\Component\DomCrawler\Crawler 
     */
    protected $parser;

    public function __construct(\TBN\AgendaBundle\Repository\AgendaRepository $repo, $url) {
        parent::__construct($repo, $url);
        $this->parser = new Crawler();

        return $this;
    }

    public function parseContent($type = "HTML") {
        $this->parser->clear();
        $this->parser->addContent(\file_get_contents($this->url), $type);

        return $this;
    }

    public function parse(\Symfony\Component\Console\Output\OutputInterface $output) {
        $links = $this->getLinks(); //Récupère les différents liens à parser depuis une page d'accueil / flux RSS

        return array_filter(array_map(function($link) {            
            try {
                $this->setUrl($link);
                $this->parseContent(); //Positionne le parser sur chaque lien
                $infos_agenda = $this->getInfosAgenda(); //Récupère les infos de l'agenda depuis le lien
                return $this->hydraterAgenda($infos_agenda); //Créé ou récupère l'agenda associé aux infos
            }catch(\Exception $e)
            {
                var_dump($link, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
                return null;
            }
        }, $links), function($agenda)
        {
            return $agenda !== null;
        });
    }

    /**
     * Retourne les infos d'un agenda depuis une url
     * @return string[]
     */
    protected abstract function getInfosAgenda();

    /**
     * Retourne les liens depuis le feed.xml
     * @return string[] le tableau des liens disponibles
     */
    public abstract function getLinks();

    /**
     * 
     * @param array $infos_agenda
     * @return Agenda l'agenda créé ou hydraté
     */
    public abstract function hydraterAgenda($infos_agenda);
}
