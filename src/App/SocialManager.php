<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Contracts\BatchResetInterface;
use App\Entity\AppOAuth;
use App\Repository\AppOAuthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SocialManager implements BatchResetInterface
{
    private bool $_siteInfoInitialized = false;

    private ?AppOAuth $appOAuth = null;

    public function __construct(
        #[Autowire(param: 'facebook_id_page')]
        private readonly string $facebookIdPage,
        #[Autowire(param: 'twitter_id_page')]
        private readonly string $twitterIdPage,
        private readonly AppOAuthRepository $appOAuthRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getAppOAuth(): ?AppOAuth
    {
        if (!$this->_siteInfoInitialized) {
            $this->_siteInfoInitialized = true;
            $this->appOAuth = $this->appOAuthRepository->findOneBy([]);
        }

        return $this->appOAuth;
    }

    /**
     * The site's own network accounts, created empty the first time an admin needs them. Persisted, not flushed.
     */
    public function getOrCreateAppOAuth(): AppOAuth
    {
        $appOAuth = $this->getAppOAuth();
        if (null === $appOAuth) {
            $appOAuth = new AppOAuth();
            $this->entityManager->persist($appOAuth);
            $this->appOAuth = $appOAuth;
        }

        return $appOAuth;
    }

    public function getFacebookIdPage(): string
    {
        return $this->facebookIdPage;
    }

    public function getTwitterIdPage(): string
    {
        return $this->twitterIdPage;
    }

    public function batchReset(): void
    {
        $this->_siteInfoInitialized = false;
        $this->appOAuth = null;
    }
}
