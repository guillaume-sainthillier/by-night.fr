<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\TwoFactor;

use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface as GoogleTwoFactorInterface;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\Passport\Credentials\TwoFactorCodeCredentials;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Rejects a 2FA code submitted for a TOTP provider the user no longer has.
 *
 * The providers of a 2FA-in-progress token are frozen at login. If the TOTP secret is removed
 * before the code is submitted (self-service recovery, or removal from another session),
 * Scheb's GoogleTotpFactory throws a TwoFactorProviderLogicException, which surfaces as a 500
 * on every retry. Turn it into an authentication failure instead, so the 2FA form tells the
 * user to log in again.
 *
 * Runs after the backup code check (priority 16) and the provider selection (priority 10),
 * before Scheb's CheckTwoFactorCodeListener (priority 0).
 */
final readonly class StaleTwoFactorProviderListener
{
    public const string MESSAGE_KEY = 'two_factor_provider_disabled';

    private const string TOTP_PROVIDER = 'google';

    #[AsEventListener(event: CheckPassportEvent::class, priority: 5)]
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(TwoFactorCodeCredentials::class)) {
            return;
        }

        $badge = $passport->getBadge(TwoFactorCodeCredentials::class);
        \assert($badge instanceof TwoFactorCodeCredentials);
        if ($badge->isResolved()) {
            return;
        }

        $token = $badge->getTwoFactorToken();
        if (self::TOTP_PROVIDER !== $token->getCurrentTwoFactorProvider()) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof GoogleTwoFactorInterface) {
            return;
        }

        $secret = $user->getGoogleAuthenticatorSecret();
        if ($user->isGoogleAuthenticatorEnabled() && null !== $secret && '' !== $secret) {
            return;
        }

        throw new CustomUserMessageAuthenticationException(self::MESSAGE_KEY);
    }
}
