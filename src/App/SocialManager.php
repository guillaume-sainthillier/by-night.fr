<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Entity\AppOAuth;
use Doctrine\ORM\EntityManagerInterface;

class SocialManager
{
    private string $facebookIdPage;

    private string $twitterIdPage;

    /**
     * @var AppOAuth
     */
    private $siteInfo;

    private EntityManagerInterface $entityManager;
    /**
     * @var App\Repository\SiteInfoRepository
     */
    private $siteInfoRepository;

    public function __construct(EntityManagerInterface $entityManager, $facebookIdPage, $twitterIdPage, \App\Repository\AppOAuthRepository $siteInfoRepository)
    {
        $this->entityManager = $entityManager;
        $this->facebookIdPage = $facebookIdPage;
        $this->twitterIdPage = $twitterIdPage;
        $this->siteInfo = false;
        $this->siteInfoRepository = $siteInfoRepository;
    }

    public function getAppOAuth(): AppOAuth
    {
        if (false === $this->siteInfo) {
            $this->siteInfo = $this->siteInfoRepository
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
