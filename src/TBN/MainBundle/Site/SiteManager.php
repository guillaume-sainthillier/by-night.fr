<?php

namespace TBN\MainBundle\Site;

use Doctrine\ORM\EntityManager;
use TBN\MainBundle\Entity\Site;
use TBN\UserBundle\Entity\SiteInfo;

class SiteManager
{
    /**
     *
     * @var Site
     */
    protected $currentSite;

    /**
     *
     * @var SiteInfo
     */
    protected $siteInfo;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    protected $isInitialized;

    public function __construct(EntityManager $entityManager)
    {
        $this->currentSite = null;
        $this->siteInfo = null;
        $this->isInitialized = false;
        $this->entityManager = $entityManager;
    }

    protected function init() {
        if(! $this->isInitialized) {
            $siteInfo = $this->entityManager
                ->getRepository('TBNUserBundle:SiteInfo')
                ->findOneBy([]);

            $this->setSiteInfo($siteInfo);
            $this->isInitialized = true;
        }
    }

    /**
     *
     * @return SiteInfo
     */
    public function getSiteInfo()
    {
        $this->init();
        return $this->siteInfo;
    }

    /**
     *
     * @param SiteInfo $siteInfo
     * @return SiteManager
     */
    public function setSiteInfo(SiteInfo $siteInfo = null)
    {
        $this->siteInfo = $siteInfo;

        return $this;
    }

    /**
     *
     * @return Site
     */
    public function getCurrentSite()
    {
        return $this->currentSite;
    }

    /**
     *
     * @param Site $currentSite
     * @return SiteManager
     */
    public function setCurrentSite(Site $currentSite = null)
    {
        $this->currentSite = $currentSite;

        return $this;
    }
}
