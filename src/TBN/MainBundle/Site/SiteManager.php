<?php

namespace TBN\MainBundle\Site;

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

    public function __construct()
    {
        $this->currentSite = null;
        $this->siteInfo = null;
    }

    /**
     *
     * @return SiteInfo
     */
    public function getSiteInfo()
    {
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
