<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Social;

use App\Social\Social;
use FOS\UserBundle\Model\UserManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;

/**
 * @Route("/{service}", requirements={"service": "facebook|twitter|google"})
 */
class DefaultController extends AbstractController
{
    /**
     * @Route("/deconnexion", name="app_disconnect_service")
     * @ParamConverter("social", options={"default_facebook_name": "facebook"})
     *
     * @return JsonResponse
     */
    public function disconnectAction(Social $social, UserCheckerInterface $userChecker, UserManagerInterface $userManager)
    {
        $user = $this->getUser();
        $social->disconnectUser($user);

        try {
            $userChecker->checkPreAuth($user);
            $userChecker->checkPostAuth($user);

            $userManager->updateUser($user);
            $userManager->reloadUser($user);
        } catch (AccountStatusException $e) {
        }

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/deconnexion/confirmation", name="app_disconnect_service_confirm")
     *
     * @param $service
     *
     * @return Response
     */
    public function disconnectConfirmAction($service)
    {
        return $this->render('Social/confirm.html.twig', [
            'service' => $service,
            'url' => $this->generateUrl('app_disconnect_service', ['service' => $service]),
        ]);
    }
}
