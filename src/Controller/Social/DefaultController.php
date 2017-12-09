<?php

namespace App\Controller\Social;

use FOS\UserBundle\Model\UserManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Social\Social;
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
     * @ParamConverter("social", options={"default_facebook_name": "facebook_events"})
     */
    public function disconnectAction(Social $social)
    {
        $user = $this->getUser();
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

        $userManager = $this->container->get(UserManagerInterface::class);
        $userManager->updateUser($user);
        $userManager->reloadUser($user);
    }
}
