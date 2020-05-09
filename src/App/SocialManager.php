<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Repository\AppOAuthRepository;
use App\Entity\AppOAuth;
use Doctrine\ORM\EntityManagerInterface;

class SocialManager
{
    private string $facebookIdPage;

    private string $twitterIdPage;

    private bool $_siteInfoInitialized = false;
    private ?AppOAuth $siteInfo = null;

    private EntityManagerInterface $entityManager;
    private AppOAuthRepository $siteInfoRepository;

    public function __construct(EntityManagerInterface $entityManager, $facebookIdPage, $twitterIdPage, AppOAuthRepository $siteInfoRepository)
    {
        $this->entityManager = $entityManager;
        $this->facebookIdPage = $facebookIdPage;
        $this->twitterIdPage = $twitterIdPage;
        $this->siteInfoRepository = $siteInfoRepository;
    }

    public function getAppOAuth(): AppOAuth
    {
        if (false === $this->_siteInfoInitialized) {
            $this->_siteInfoInitialized = true;
            $this->siteInfo = $this->siteInfoRepository->findOneBy([]);
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
