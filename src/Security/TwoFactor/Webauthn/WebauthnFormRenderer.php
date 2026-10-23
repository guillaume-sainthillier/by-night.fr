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
use LogicException;
use Override;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The 2FA form when the passkey is the active provider: the same template as the TOTP one, with a fresh challenge
 */
final readonly class WebauthnFormRenderer implements TwoFactorFormRendererInterface
{
    public const string TEMPLATE = 'security/2fa-login.html.twig';

    public function __construct(
        private Security $security,
        private Environment $twig,
        private WebauthnAuthenticator $authenticator,
        private WebauthnOptionsStorage $storage,
        private WebauthnCeremonies $ceremonies,
    ) {
    }

    /**
     * @param array<string, mixed> $templateVars
     */
    #[Override]
    public function renderForm(Request $request, array $templateVars): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new LogicException('The passkey form needs a User waiting for their second factor.');
        }

        $options = $this->authenticator->createRequestOptions($user);
        $this->storage->setRequestOptions($options);

        return new Response($this->twig->render(self::TEMPLATE, [
            ...$templateVars,
            'webauthnRequestOptions' => $this->ceremonies->toJson($options),
        ]));
    }
}
