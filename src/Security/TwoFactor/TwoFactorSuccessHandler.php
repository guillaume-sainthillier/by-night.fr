<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\TwoFactor;

use App\Security\LoginTargetPath;
use Override;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * A login completed by its second factor lands like one by the form (UserFormAuthenticator): the page the member
 * logged in from (LoginTargetPath), else their personal space. The bundle's default would go to "/".
 */
final readonly class TwoFactorSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private LoginTargetPath $loginTargetPath,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        return new RedirectResponse(
            $this->loginTargetPath->pull($request->getSession()) ?? $this->urlGenerator->generate('app_personal_space_events'),
        );
    }
}
