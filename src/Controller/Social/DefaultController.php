<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Social;

use App\Controller\AbstractController;
use App\Security\UserSocialAuthenticator;
use App\Social\Social;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route(path: '/{service<%patterns.social%>}')]
final class DefaultController extends AbstractController
{
    #[Route(path: '/deconnexion', name: 'app_disconnect_service', methods: ['POST'])]
    public function disconnect(Social $social, Request $request, UserSocialAuthenticator $socialAuthenticator, TokenStorageInterface $tokenStorage): Response
    {
        $user = $this->getAppUser();
        $social->disconnectUser($user);
        $this->getEntityManager()->flush();

        // Reload user roles as they have changed. A session a remember-me cookie opened stays one: a login would turn
        // it into a session logged in afresh, which opens the pages that need one (SecurityController)
        $token = $tokenStorage->getToken();
        if ($token instanceof RememberMeToken) {
            $tokenStorage->setToken(new RememberMeToken($user, $token->getFirewallName()));
        } else {
            $socialAuthenticator->login($user, $request);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route(path: '/deconnexion/confirmation', name: 'app_disconnect_service_confirm', methods: ['GET'])]
    public function disconnectConfirm(string $service): Response
    {
        return $this->render('social/confirm.html.twig', [
            'service' => $service,
            'url' => $this->generateUrl('app_disconnect_service', ['service' => $service]),
        ]);
    }
}
