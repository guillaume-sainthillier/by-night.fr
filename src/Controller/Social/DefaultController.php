<?php

namespace App\Controller\Social;

use App\Social\Social;
use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccountStatusException;

/**
 * @Route("/{service}", requirements={"service": "facebook|twitter|google"})
 */
class DefaultController extends BaseController
{
    /**
     * @Route("/deconnexion", name="tbn_disconnect_service")
     * @ParamConverter("social", options={"default_facebook_name": "facebook_events"})
     *
     * @param Social $social
     *
     * @return JsonResponse
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
     *
     * @param $service
     *
     * @return \Symfony\Component\HttpFoundation\Response
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
