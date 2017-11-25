<?php

namespace AppBundle\Controller\Social;

use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use AppBundle\Social\Social;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{service}", requirements={"service": "facebook|twitter|google"})
 */
class DefaultController extends BaseController
{
    /**
     * @Route("/deconnexion", name="tbn_disconnect_service")
     */
    public function disconnectAction($service)
    {
        $user = $this->getUser();

        /** @var Social $social */
        $social = $this->container->get('tbn.social.' . \strtolower('facebook' === $service ? 'facebook_events' : $service));
        $social->disconnectUser($user);

        $this->authenticateBasicUser($user);

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/deconnexion/confirmation", name="tbn_disconnect_service_confirm")
     */
    public function disconnectConfirmAction($service)
    {
        return $this->render('Social/confirm.html.twig', [
            'service' => $service,
            'url'     => $this->generateUrl('tbn_disconnect_service', ['service' => $service]),
        ]);
    }

    /**
     * Authenticate a user with Symfony Security.
     *
     * @param UserInterface $user
     */
    protected function authenticateBasicUser(UserInterface $user)
    {
        try {
            $this->container->get('hwi_oauth.user_checker')->checkPreAuth($user);
            $this->container->get('hwi_oauth.user_checker')->checkPostAuth($user);
        } catch (AccountStatusException $e) {
            // Don't authenticate locked, disabled or expired users
            return;
        }

        $userManager = $this->container->get('fos_user.user_manager');
        $userManager->updateUser($user);
        $userManager->reloadUser($user);
    }
}
