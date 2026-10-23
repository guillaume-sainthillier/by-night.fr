<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\TwoFactor\Webauthn;

use App\Entity\User;
use Override;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The passkey second factor of scheb/2fa-bundle, for a User who registered one
 */
#[AutoconfigureTag('scheb_two_factor.provider', ['alias' => self::ALIAS])]
final readonly class WebauthnTwoFactorProvider implements TwoFactorProviderInterface
{
    public const string ALIAS = 'webauthn';

    public function __construct(
        private WebauthnFormRenderer $formRenderer,
        private WebauthnAuthenticator $authenticator,
        private WebauthnOptionsStorage $storage,
    ) {
    }

    #[Override]
    public function beginAuthentication(AuthenticationContextInterface $context): bool
    {
        $user = $context->getUser();

        return $user instanceof User && $user->isWebauthnAuthenticatorEnabled();
    }

    #[Override]
    public function prepareAuthentication(object $user): void
    {
        // The challenge is created with the form, each time it is shown
    }

    #[Override]
    public function validateAuthenticationCode(object $user, string $authenticationCode): bool
    {
        $options = $this->storage->getRequestOptions();
        if (!$user instanceof User || null === $options) {
            return false;
        }

        return $this->authenticator->check($user, $options, $authenticationCode);
    }

    #[Override]
    public function getFormRenderer(): TwoFactorFormRendererInterface
    {
        return $this->formRenderer;
    }
}
