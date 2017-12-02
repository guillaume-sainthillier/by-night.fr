<?php

namespace AppBundle\App;

use AppBundle\Entity\SiteInfo;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 14/12/2016
 * Time: 21:57.
 */
class SocialManager
{
    /**
     * @var string
     */
    private $facebookIdPage;

    /**
     * @var string
     */
    private $twitterIdPage;

    /**
     * @var string
     */
    private $googleIdPage;

    /**
     * @var SiteInfo
     */
    private $siteInfo;

    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(ObjectManager $entityManager, $facebookIdPage, $twitterIdPage, $googleIdPage)
    {
        $this->entityManager  = $entityManager;
        $this->facebookIdPage = $facebookIdPage;
        $this->twitterIdPage  = $twitterIdPage;
        $this->googleIdPage   = $googleIdPage;
        $this->siteInfo       = false;
    }

    /**
     * @return SiteInfo
     */
    public function getSiteInfo()
    {
        if (false === $this->siteInfo) {
            $this->siteInfo = $this->entityManager
                ->getRepository('AppBundle:SiteInfo')
                ->findOneBy([]);
        }

        return $this->siteInfo;
    }

    /**
     * @return string
     */
    public function getFacebookIdPage()
    {
        return $this->facebookIdPage;
    }

    /**
     * @return string
     */
    public function getTwitterIdPage()
    {
        return $this->twitterIdPage;
    }

    /**
     * @return string
     */
    public function getGoogleIdPage()
    {
        return $this->googleIdPage;
    }
}
