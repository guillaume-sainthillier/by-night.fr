<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthenticationHandler implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface
{
    protected TranslatorInterface $translator;

    protected RouterInterface $router;

    public function __construct(TranslatorInterface $translator, RouterInterface $router)
    {
        $this->translator = $translator;
        $this->router = $router;
    }

    /**
     * @return JsonResponse|RedirectResponse
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        if ($request->isXmlHttpRequest()) {
            $result = ['success' => true];

            return new JsonResponse($result);
        }
        $key = '_security.main.target_path'; //where "main" is your firewall name

        if ($targetPath = $request->getSession()->get($key)) {
            $url = $targetPath;
        } elseif ($request->getSession()->has($key)) {
            //set the url based on the link they were trying to access before being authenticated
            $url = $request->getSession()->get($key);
            //remove the session key
            $request->getSession()->remove($key);
        } else {
            $user = $token->getUser();

            if ($user->getCity()) {
                $url = $this->router->generate('app_agenda_index', ['location' => $user->getCity()->getSlug()]);
            } else {
                $url = $this->router->generate('app_main_index');
            }
        }

        return new RedirectResponse($url);
    }

    /**
     * @return JsonResponse|RedirectResponse
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        if ($request->isXmlHttpRequest()) {
            $result = [
                'success' => false,
                'message' => $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(), 'security'),
            ];

            return new JsonResponse($result);
        }

        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        $url = $this->router->generate('app_login');

        return new RedirectResponse($url);
    }
}
