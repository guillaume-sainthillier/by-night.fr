<?php

namespace App\App;

use App\Entity\SiteInfo;
use Doctrine\ORM\EntityManagerInterface;

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
     * @var SiteInfo
     */
    private $siteInfo;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager, $facebookIdPage, $twitterIdPage)
    {
        $this->entityManager = $entityManager;
        $this->facebookIdPage = $facebookIdPage;
        $this->twitterIdPage = $twitterIdPage;
        $this->siteInfo = false;
    }

    public function getSiteInfo(): SiteInfo
    {
        if (false === $this->siteInfo) {
            $this->siteInfo = $this->entityManager
                ->getRepository(SiteInfo::class)
                ->findOneBy([]);
        }

        return $this->siteInfo;
    }

    public function getFacebookIdPage(): string
    {
        return $this->facebookIdPage;
    }

    public function getTwitterIdPage(): string
    {
        return $this->twitterIdPage;
    }
}
