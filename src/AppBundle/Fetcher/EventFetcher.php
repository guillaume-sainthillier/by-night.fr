<?php

namespace TBN\MajDataBundle\Fetcher;
use Doctrine\ORM\EntityManager;
use TBN\MajDataBundle\Parser\Common\FaceBookParser;
use TBN\MajDataBundle\Parser\Manager\ParserManager;
use TBN\MajDataBundle\Parser\ParserInterface;

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

    public function __construct(ParserManager $parserManager, EntityManager $entityManager) {
        $this->parserManager = $parserManager;
        $this->entityManager = $entityManager;
    }

    public function fetchEvents(ParserInterface $parser) {
        if($parser instanceof FaceBookParser) {
            $siteInfo = $this->getSiteInfo();
            $parser->setSiteInfo($siteInfo);
        }

        $this->parserManager->add($parser);

        return $this->parserManager->getAgendas();
    }

    protected function getSiteInfo() {
        $siteInfo = $this->entityManager->getRepository('TBNUserBundle:SiteInfo')->findOneBy([]);

        if(! $siteInfo) {
            throw new \RuntimeException("Aucun site info enregistré");
        }

        if(! $siteInfo->getFacebookAccessToken()) {
            throw new \RuntimeException("Le site info n'est pas configuré avec Facebook");
        }

        return $siteInfo;
    }
}
