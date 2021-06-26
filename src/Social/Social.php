<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Social;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * && open the template in the editor.
 */

use App\App\SocialManager;
use App\Entity\AppOAuth;
use App\Entity\OAuth;
use App\Entity\User;
use App\Exception\SocialException;
use App\Picture\EventProfilePicture;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

abstract class Social
{
    protected string $id;

    protected array $config;

    protected string $secret;

    protected TokenStorageInterface $tokenStorage;

    protected RouterInterface $router;

    protected SessionInterface $session;

    protected RequestStack $requestStack;

    protected LoggerInterface $logger;

    protected EventProfilePicture $eventProfilePicture;

    protected SocialManager $socialManager;

    protected bool $isInitialized;

    public function __construct(array $config, TokenStorageInterface $tokenStorage, RouterInterface $router, SessionInterface $session, RequestStack $requestStack, LoggerInterface $logger, EventProfilePicture $eventProfilePicture, SocialManager $socialManager)
    {
        if (!isset($config['id'])) {
            throw new SocialException("Le paramètre 'id' est absent");
        }

        if (!isset($config['secret'])) {
            throw new SocialException("Le paramètre 'secret' est absent");
        }

        $this->id = $config['id'];
        $this->secret = $config['secret'];
        $this->config = $config;
        $this->tokenStorage = $tokenStorage;
        $this->router = $router;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->eventProfilePicture = $eventProfilePicture;
        $this->socialManager = $socialManager;
        $this->isInitialized = false;
    }

    abstract public function getInfoPropertyPrefix(): ?string;

    abstract protected function getRoleName(): string;

    public function connectSite(AppOAuth $info, array $datas): void
    {
        $this->connectInfo($info, $datas);
    }

    public function disconnectSite(AppOAuth $info): void
    {
        $this->disconnectInfo($info);
    }

    public function connectUser(User $user, array $datas): void
    {
        $user->addRole($this->getRoleName());
        $this->connectInfo($user->getOAuth(), $datas);
    }

    public function disconnectUser(User $user): void
    {
        $user->removeRole($this->getRoleName());
        $this->disconnectInfo($user->getOAuth());
    }

    protected function connectInfo(OAuth $info, array $datas): void
    {
        $propertyPrefix = $this->getInfoPropertyPrefix();
        $propertyAccess = PropertyAccess::createPropertyAccessor();

        foreach ($this->getInfoProperties() as $property) {
            if (empty($datas[$property])) {
                continue;
            }
            $value = $datas[$property];
            $fullProperty = $propertyPrefix . ucfirst($property);
            $propertyAccess->setValue($info, $fullProperty, $value);
        }
    }

    protected function disconnectInfo(OAuth $info): void
    {
        $propertyPrefix = $this->getInfoPropertyPrefix();
        $propertyAccess = PropertyAccess::createPropertyAccessor();

        foreach ($this->getInfoProperties() as $property) {
            $fullProperty = $propertyPrefix . ucfirst($property);
            $propertyAccess->setValue($info, $fullProperty, null);
        }
    }

    protected function getInfoProperties(): array
    {
        return ['id', 'accessToken', 'refreshToken', 'expires', 'realname', 'email', 'profilePicture'];
    }

    protected function init(): void
    {
        if (!$this->isInitialized) {
            $this->isInitialized = true;
            $this->constructClient();
        }
    }

    abstract protected function constructClient(): void;
}
