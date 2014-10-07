<?php

namespace TBN\MainBundle\Site;

use TBN\MainBundle\Entity\Site;

class SiteManager
{
    /**
     *
     * @var TBN\MainBundle\Entity\Site 
     */
    protected $currentSite;

    public function __construct()
    {
        $this->currentSite = null;
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
    public function setCurrentSite(Site $currentSite)
    {
        $this->currentSite = $currentSite;
    }
}
