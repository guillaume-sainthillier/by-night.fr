<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Entity\AppOAuth;
use App\Repository\AppOAuthRepository;

class SocialManager
{
    private string $facebookIdPage;
    private string $twitterIdPage;
    private bool $_siteInfoInitialized = false;
    private ?AppOAuth $appOAuth = null;

    private AppOAuthRepository $appOAuthRepository;

    public function __construct(string $facebookIdPage, string $twitterIdPage, AppOAuthRepository $appOAuthRepository)
    {
        $this->facebookIdPage = $facebookIdPage;
        $this->twitterIdPage = $twitterIdPage;
        $this->appOAuthRepository = $appOAuthRepository;
    }

    public function hasAppOAuth(): bool
    {
        if (!$this->_siteInfoInitialized) {
            $this->_siteInfoInitialized = true;
            $this->appOAuth = $this->appOAuthRepository->findOneBy([]);
        }

        return null !== $this->appOAuth;
    }

    public function getAppOAuth(): AppOAuth
    {
        if (!$this->_siteInfoInitialized) {
            $this->_siteInfoInitialized = true;
            $this->appOAuth = $this->appOAuthRepository->findOneBy([]);
        }

        return $this->appOAuth;
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
