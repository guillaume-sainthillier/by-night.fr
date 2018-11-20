<?php

namespace App\Controller\Admin;

use App\App\SocialManager;
use App\Social\Social;
use App\Social\SocialProvider;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/social/{service}", requirements={"service": "facebook|twitter|google"})
 */
class SocialController extends Controller
{
    /**
     * @Route("/connexion", name="tbn_administration_connect_site")
     *
     * @param $service
     * @param SessionInterface $session
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function connectInfoAction($service, SessionInterface $session)
    {
        $session->set('connect_site', true);

        $url = $this->generateUrl('hwi_oauth_service_redirect', [
            'service' => SocialProvider::FACEBOOK === $service ? SocialProvider::FACEBOOK_ADMIN : $service,
        ]);

        return $this->redirect($url);
    }

    /**
     * @Route("/deconnexion", name="tbn_administration_disconnect_service")
     * @ParamConverter("social", options={"default_facebook_name": "facebook_admin"})
     *
     * @param $service
     * @param Social $social
     *
     * @return JsonResponse
     */
    public function disconnectSiteAction(Social $social)
    {
        $social->disconnectSite();

        $em = $this->getDoctrine()->getManager();
        $em->persist($this->get(SocialManager::class)->getSiteInfo());
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/deconnexion/confirmation", name="tbn_administration_disconnect_service_confirm")
     *
     * @param $service
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function disconnectConfirmAction($service)
    {
        return $this->render('Social/confirm.html.twig', [
            'service' => $service,
            'url'     => $this->generateUrl('tbn_administration_disconnect_service', ['service' => $service]),
        ]);
    }
}
