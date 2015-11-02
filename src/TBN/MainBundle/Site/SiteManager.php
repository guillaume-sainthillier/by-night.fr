<?php

namespace TBN\MainBundle\Site;

use TBN\MainBundle\Entity\Site;
use TBN\UserBundle\Entity\SiteInfo;

class SiteManager
{
    /**
     *
     * @var TBN\MainBundle\Entity\Site 
     */
    protected $currentSite;
    
    /**
     *
     * @var TBN\UserBundle\Entity\SiteInfo
     */
    protected $siteInfo;

    public function __construct()
    {
        $this->currentSite = null;
        $this->siteInfo = null;
    }
    
    /**
     * 
     * @return TBN\UserBundle\Entity\SiteInfo
     */
    public function getSiteInfo()
    {
        return $this->siteInfo;
    }

    /**
     * 
     * @param TBN\UserBundle\Entity\SiteInfo $siteInfo
     */
    public function setSiteInfo(SiteInfo $siteInfo = null)
    {
        $this->siteInfo = $siteInfo;
        
        return $this;
    }
    
    /**
     * 
     * @return TBN\MainBundle\Entity\Site
     */
    public function getCurrentSite()
    {
        return $this->currentSite;
    }

    /**
     * 
     * @param \TBN\MainBundle\Entity\Site $currentSite
     */
    public function setCurrentSite(Site $currentSite = null)
    {
        $this->currentSite = $currentSite;
        
        return $this;
    }
}
