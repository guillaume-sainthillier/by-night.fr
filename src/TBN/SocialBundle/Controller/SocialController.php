<?php

namespace TBN\SocialBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use TBN\UserBundle\Entity\User;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

class SocialController extends BaseController
{

    public function disconnectSiteAction($service)
    {
        /** @var social Social */
        $social = $this->container->get("tbn.social." . strtolower($service === "facebook" ? "facebook_events" : $service));
        $siteManager = $this->container->get("site_manager");
        $currentSite = $siteManager->getCurrentSite();
        $social->disconnectSite($currentSite);//On enlève le profil social du site
        $em = $this->container->get("doctrine.orm.entity_manager");
        $em->persist($currentSite);
        $em->flush();


        return new JsonResponse(["success" => true]);
    }

    public function disconnectAction(Request $request, $service)
    {
        $user = $this->getUserWithService($request, $service);
        /** @var social Social */
        $social = $this->container->get("tbn.social." . strtolower($service === "facebook" ? "facebook_events" : $service));
        $social->disconnectUser($user);
        $this->authenticateBasicUser($user);

        return new JsonResponse(["success" => true]);
    }

    public function disconnectConfirmAction(Request $request, $service, $from_site = false)
    {
        $this->getUserWithService($request, $service);

        return $this->render('TBNSocialBundle:Social:confirm_disconnect_' . ($from_site ? "site_" : "") . $service . '.html.twig', [
            "service" => $service
        ]);
    }

    /**
     *
     * @param type $service
     * @return User xxx
     * @throws type
     * @throws AccessDeniedException
     */
    protected function getUserWithService(Request $request, $service)
    {
        if (!$request->isXmlHttpRequest()) {
            throw $this->createNotFoundException('La page demandée est introuvable');
        }

        $user = $this->getUser();

        return $user;
    }

    /**
     * Authenticate a user with Symfony Security
     *
     * @param UserInterface $user
     * @param string $resourceOwnerName
     * @param string $accessToken
     * @param boolean $fakeLogin
     */
    protected function authenticateBasicUser(UserInterface $user)
    {
        try {
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
