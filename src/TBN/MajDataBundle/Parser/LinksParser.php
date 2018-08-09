<?php

namespace TBN\MajDataBundle\Parser;

use Symfony\Component\DomCrawler\Crawler;
use TBN\MajDataBundle\Utils\Monitor;

/**
 * @author Guillaume S. <guillaume@sainthillier.fr>
 */
abstract class LinksParser extends AgendaParser
{
    /*
     * @var Crawler $parser
     */
    protected $parser;

    protected $base_url;

    public function __construct()
    {
        parent::__construct();

        $this->url      = null;
        $this->base_url = null;
        $this->parser   = new Crawler();

        return $this;
    }

    public function parseContent($type = 'HTML')
    {
        $this->parser->clear();

        try {
            $this->parser->addContent(\file_get_contents($this->url), $type);
        } catch (\Exception $e) {
            Monitor::writeException($e);
        }

        return $this;
    }

    public function getRawAgendas()
    {
        $links = $this->getLinks(); //Récupère les différents liens à parser depuis une page d'accueil / flux RSS

        $agendas = [];

        foreach ($links as $link) {
            $this->setURL($link);
            $this->parseContent(); //Positionne le parser sur chaque lien

            try {
                $infosAgenda = $this->getInfosAgenda();

                if (!$this->isMultiArray($infosAgenda)) {
                    $infosAgenda = [$infosAgenda];
                }

                $agendas = \array_merge($agendas, $infosAgenda);
            } catch (\Exception $e) {
                Monitor::writeException($e);
            }
        }

        return $agendas;
    }

    protected function getSilentNode(Crawler $node)
    {
        if (0 === $node->count()) {
            return;
        }

        return $node;
    }

    private function isMultiArray($array)
    {
        return \count(\array_filter($array, 'is_array')) > 0;
    }

    public function getBaseUrl()
    {
        return $this->base_url;
    }

    public function setBaseUrl($base_url)
    {
        $this->base_url = $base_url;

        return $this;
    }

    /**
     * Retourne les infos d'un agenda depuis une url.
     *
     * @return string[]
     */
    abstract protected function getInfosAgenda();

    /**
     * Retourne les liens depuis le feed.xml.
     *
     * @return string[] le tableau des liens disponibles
     */
    abstract public function getLinks();
}
