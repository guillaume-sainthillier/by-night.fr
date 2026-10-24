<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\TwoFactor;

use App\Entity\User;
use Override;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Condition\TwoFactorConditionInterface;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Asks for the second factor when someone logs in, not when a member already logged in is authenticated again:
 * UserSocialAuthenticator::login() reloads their roles after a network is disconnected, and connecting a network from
 * their account goes through the social login. Their session already passed the second factor.
 *
 * Unless a remember-me cookie opened it, which never asks for the second factor: logging in again from it is a login,
 * the one the pages needing a fresh login (SecurityController) send to.
 */
final readonly class LoginTwoFactorCondition implements TwoFactorConditionInterface
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    #[Override]
    public function shouldPerformTwoFactorAuthentication(AuthenticationContextInterface $context): bool
    {
        // The token of the session, not yet replaced by the one being created
        $current = $this->tokenStorage->getToken();
        if (null === $current || $current instanceof TwoFactorTokenInterface || $current instanceof RememberMeToken) {
            return true;
        }

        // By id: the identifier is the e-mail, empty for every member a network logged in without one
        $currentUser = $current->getUser();
        $user = $context->getUser();

        return !$currentUser instanceof User || !$user instanceof User || $currentUser->getId() !== $user->getId();
    }
}
