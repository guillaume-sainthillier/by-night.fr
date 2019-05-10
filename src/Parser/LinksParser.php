<?php

namespace App\Parser;

use App\Utils\Monitor;
use Exception;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @author Guillaume S. <guillaume@sainthillier.fr>
 */
abstract class LinksParser extends EventParser
{
    /*
     * @var Crawler $parser
     */
    protected $parser;

    protected $base_url;

    public function __construct()
    {
        $this->url = null;
        $this->base_url = null;
        $this->parser = new Crawler();

        return $this;
    }

    public function parseContent($type = 'HTML')
    {
        $this->parser->clear();

        try {
            $this->parser->addContent(\file_get_contents($this->url), $type);
        } catch (Exception $e) {
            Monitor::writeException($e);
        }

        return $this;
    }

    public function getRawEvents()
    {
        $links = $this->getLinks(); //Récupère les différents liens à parser depuis une page d'accueil / flux RSS

        $events = [];

        foreach ($links as $link) {
            $this->setURL($link);
            $this->parseContent(); //Positionne le parser sur chaque lien

            try {
                $infosEvent = $this->getInfosEvent();

                if (!$this->isMultiArray($infosEvent)) {
                    $infosEvent = [$infosEvent];
                }

                $events = \array_merge($events, $infosEvent);
            } catch (Exception $e) {
                Monitor::writeException($e);
            }
        }

        return $events;
    }

    protected function getSilentNode(Crawler $node)
    {
        if (0 === $node->count()) {
            return null;
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
     * Retourne les infos d'un event depuis une url.
     *
     * @return string[]
     */
    abstract protected function getInfosEvent();

    /**
     * Retourne les liens depuis le feed.xml.
     *
     * @return string[] le tableau des liens disponibles
     */
    abstract public function getLinks();
}
