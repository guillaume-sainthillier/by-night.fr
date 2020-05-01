<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\App\SocialManager;
use App\Social\Social;
use App\Social\SocialProvider;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/social/{service}", requirements={"service": "facebook|twitter|google"})
 */
class SocialController extends AbstractController
{
    /**
     * @Route("/connexion", name="app_administration_connect_site")
     *
     * @param $service
     *
     * @return RedirectResponse
     */
    public function connectInfo($service, SessionInterface $session)
    {
        $session->set('connect_site', true);

        $url = $this->generateUrl('hwi_oauth_service_redirect', [
            'service' => SocialProvider::FACEBOOK === $service ? SocialProvider::FACEBOOK_ADMIN : $service,
        ]);

        return $this->redirect($url);
    }

    /**
     * @Route("/deconnexion", name="app_administration_disconnect_service")
     * @ParamConverter("social", options={"default_facebook_name": "facebook_admin"})
     *
     * @return JsonResponse
     */
    public function disconnectSite(Social $social, SocialManager $socialManager)
    {
        $social->disconnectSite();

        $em = $this->getDoctrine()->getManager();
        $em->persist($socialManager->getSiteInfo());
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/deconnexion/confirmation", name="app_administration_disconnect_service_confirm")
     *
     * @param $service
     *
     * @return Response
     */
    public function disconnectConfirm($service)
    {
        return $this->render('Social/confirm.html.twig', [
            'service' => $service,
            'url' => $this->generateUrl('app_administration_disconnect_service', ['service' => $service]),
        ]);
    }
}
