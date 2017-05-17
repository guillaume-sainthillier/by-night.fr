<?php

namespace AppBundle\Fetcher;

use AppBundle\Site\SiteManager;
use Doctrine\ORM\EntityManager;
use AppBundle\Parser\Common\FaceBookParser;
use AppBundle\Parser\Manager\ParserManager;
use AppBundle\Parser\ParserInterface;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 26/11/2016
 * Time: 13:17
 */
class EventFetcher
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var ParserManager
     */
    protected $parserManager;

    /**
     * @var SiteManager
     */
    protected $siteManager;

    public function __construct(ParserManager $parserManager, EntityManager $entityManager, SiteManager $siteManager)
    {
        $this->parserManager = $parserManager;
        $this->entityManager = $entityManager;
        $this->siteManager = $siteManager;
    }

    /**
     * @param ParserInterface $parser
     * @return array
     */
    public function fetchEvents(ParserInterface $parser)
    {
        if ($parser instanceof FaceBookParser) {
            $siteInfo = $this->getSiteInfo();
            $parser->setSiteInfo($siteInfo);
        }

        $this->parserManager->add($parser);

        return $this->parserManager->getAgendas();
    }

    /**
     * @return \AppBundle\Entity\SiteInfo
     */
    protected function getSiteInfo()
    {
        $siteInfo = $this->siteManager->getSiteInfo();

        if (!$siteInfo) {
            throw new \RuntimeException("Aucun site info enregistré");
        }

        if (!$siteInfo->getFacebookAccessToken()) {
            throw new \RuntimeException("Le site info n'est pas configuré avec Facebook");
        }

        return $siteInfo;
    }
}
