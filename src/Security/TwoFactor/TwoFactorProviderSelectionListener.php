<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\TwoFactor;

use App\Security\TwoFactor\Webauthn\WebauthnTwoFactorProvider;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\Passport\Credentials\TwoFactorCodeCredentials;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Lets the authenticator-code form and the passkey live (and both work) on the same 2FA page.
 *
 * Scheb validates the submitted code against the single *active* provider chosen at render time —
 * it never reads `preferProvider` on the check POST — so two things are needed:
 *
 *  - onTwoFactorForm: keep WebAuthn the active provider whenever the form is shown, so its challenge
 *    is always rendered (the passkey can't start without it). This survives a failed code attempt,
 *    which would otherwise flip the active provider to TOTP and hide the passkey for the session.
 *  - onCheckPassport: each form carries a hidden `auth_type`; select + flag-prepare the matching
 *    provider before Scheb's CheckTwoFactorCodeListener (priority 0) so a code is validated by the
 *    TOTP provider and an assertion by the WebAuthn provider. `prepareAuthentication()` is a no-op
 *    for both, so flagging prepared is enough.
 *
 * Both forms are in templates/security/2fa-login.html.twig.
 */
final readonly class TwoFactorProviderSelectionListener
{
    private const string TOTP_PROVIDER = 'google';

    private const string WEBAUTHN_PROVIDER = WebauthnTwoFactorProvider::ALIAS;

    private const array PROVIDER_BY_AUTH_TYPE = [
        'totp' => self::TOTP_PROVIDER,
        'webauthn' => self::WEBAUTHN_PROVIDER,
    ];

    public function __construct(private RequestStack $requestStack)
    {
    }

    #[AsEventListener(event: TwoFactorAuthenticationEvents::FORM)]
    public function onTwoFactorForm(TwoFactorAuthenticationEvent $event): void
    {
        $token = $event->getToken();
        if ($token instanceof TwoFactorTokenInterface
            && \in_array(self::WEBAUTHN_PROVIDER, $token->getTwoFactorProviders(), true)) {
            $token->preferTwoFactorProvider(self::WEBAUTHN_PROVIDER);
        }
    }

    #[AsEventListener(event: CheckPassportEvent::class, priority: 10)]
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(TwoFactorCodeCredentials::class)) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $providerName = self::PROVIDER_BY_AUTH_TYPE[(string) $request?->getPayload()->get('auth_type', '')] ?? null;
        if (null === $providerName) {
            return;
        }

        $badge = $passport->getBadge(TwoFactorCodeCredentials::class);
        \assert($badge instanceof TwoFactorCodeCredentials);
        $token = $badge->getTwoFactorToken();

        // preferTwoFactorProvider() throws if the provider is not on the token — this guard also
        // ignores a forged auth_type for a provider the user has not enabled.
        if (!\in_array($providerName, $token->getTwoFactorProviders(), true)) {
            return;
        }

        $token->preferTwoFactorProvider($providerName);
        $token->setTwoFactorProviderPrepared($providerName);
    }
}
