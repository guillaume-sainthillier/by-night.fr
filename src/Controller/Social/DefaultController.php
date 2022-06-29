<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/{service<%patterns.social%>}')]
class DefaultController extends AbstractController
{
    #[Route(path: '/deconnexion', name: 'app_disconnect_service', methods: ['POST'])]
    public function disconnect(Social $social, Request $request, UserSocialAuthenticator $socialAuthenticator): Response
    {
        $user = $this->getAppUser();
        $social->disconnectUser($user);
        $this->getEntityManager()->flush();

        // Reload user roles as they have changed
        $socialAuthenticator->login($user, $request);

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
