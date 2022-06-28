<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\App\SocialManager;
use App\Social\Social;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/social/{service<%patterns.admin_social%>}")
 */
class SocialController extends AbstractController
{
    /**
     * @Route("/deconnexion", name="app_administration_disconnect_service", methods={"POST"})
     */
    public function disconnect(Social $social, SocialManager $socialManager): Response
    {
        $appOAuth = $socialManager->getAppOAuth();
        $social->disconnectSite($appOAuth);

        $em = $this->getDoctrine()->getManager();
        $em->persist($appOAuth);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/deconnexion/confirmation", name="app_administration_disconnect_service_confirm", methods={"GET"})
     */
    public function disconnectConfirm(string $service): Response
    {
        return $this->render('social/confirm.html.twig', [
            'service' => $service,
            'url' => $this->generateUrl('app_administration_disconnect_service', ['service' => $service]),
        ]);
    }
}
