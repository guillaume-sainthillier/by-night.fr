<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Social;

use App\Security\UserSocialAuthenticator;
use App\Social\Social;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;

/**
 * @Route("/{service<%patterns.social%>}")
 */
class DefaultController extends AbstractController
{
    /**
     * @Route("/deconnexion", name="app_disconnect_service", methods={"POST"})
     */
    public function disconnect(Social $social, Request $request, GuardAuthenticatorHandler $guardAuthenticatorHandler, UserSocialAuthenticator $socialAuthenticator): Response
    {
        $user = $this->getUser();
        $social->disconnectUser($user);
        $this->getDoctrine()->getManager()->flush();

        //Reload user roles as they have changed
        $token = $socialAuthenticator->createAuthenticatedToken($user, 'main');
        $guardAuthenticatorHandler->authenticateWithToken($token, $request);

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/deconnexion/confirmation", name="app_disconnect_service_confirm", methods={"GET"})
     */
    public function disconnectConfirm(string $service): Response
    {
        return $this->render('Social/confirm.html.twig', [
            'service' => $service,
            'url' => $this->generateUrl('app_disconnect_service', ['service' => $service]),
        ]);
    }
}
