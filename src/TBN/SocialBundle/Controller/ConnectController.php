<?php

/*
 * This file is part of the HWIOAuthBundle package.
 *
 * (c) Hardware.Info <opensource@hardware.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace TBN\SocialBundle\Controller;

use HWI\Bundle\OAuthBundle\Controller\ConnectController as BaseController;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

use TBN\MainBundle\Entity\Site;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;
/**
 * ConnectController
 *
 * @author Alexander <iam.asm89@gmail.com>
 */
class ConnectController extends BaseController
{

    /**
     * Connects a user to a given account if the user is logged in and connect is enabled.
     *
     * @param Request $request The active request.
     * @param string  $service Name of the resource owner to connect to.
     *
     * @throws \Exception
     *
     * @return Response
     *
     * @throws NotFoundHttpException if `connect` functionality was not enabled
     * @throws AccessDeniedException if no user is authenticated
     */
    public function connectServiceAction(Request $request, $service)
    {
        $connect = $this->container->getParameter('hwi_oauth.connect');
        if (!$connect) {
            throw new NotFoundHttpException();
        }

        $hasUser = $this->container->get('security.context')->isGranted('IS_AUTHENTICATED_REMEMBERED');
        if (!$hasUser) {
            throw new AccessDeniedException('Cannot connect an account.');
        }

        // Get the data from the resource owner
        $resourceOwner = $this->getResourceOwnerByName($service);

        $session = $request->getSession();

        $key = $request->query->get('key', time());

        if ($resourceOwner->handles($request)) {
            $accessToken = $resourceOwner->getAccessToken(
                $request,
                $this->generate('hwi_oauth_connect_service', ['service' => $service], true)
            );

            // save in session
            $session->set('_hwi_oauth.connect_confirmation.'.$key, $accessToken);
        } else {
            $accessToken = $session->get('_hwi_oauth.connect_confirmation.'.$key);
        }

        $userInformation = $resourceOwner->getUserInformation($accessToken);

        // Show confirmation page?
        if (!$this->container->getParameter('hwi_oauth.connect.confirmation')) {
            goto show_confirmation_page;
        }

        // Handle the form
        /** @var $form FormInterface */
        $form = $this->container->get('form.factory')
            ->createBuilder('form')
            ->getForm();

        if ($request->isMethod('POST')) {
            $form->bind($request);

            if ($form->isValid()) {
                show_confirmation_page:

                $session = $this->container->get('session');
                if($session->has('connect_site')) // On veut connecter le site et non l'utilisateur
                {
                    $session->remove('connect_site');
                    $siteManager = $this->container->get("site_manager");
                    $currentSite = $siteManager->getCurrentSite();

                    $this->container->get('hwi_oauth.account.connector')->connectSite($currentSite, $userInformation);

                    $em = $this->container->get("doctrine.orm.entity_manager");

                    $em->persist($currentSite);
                    $em->flush();

                    $cache = $this->container->get("winzou_cache");
		    $key = $currentSite->getSubdomain();
		    if($cache->contains($key))
		    {
			$cache->delete($key);
		    }
		    $cache->save($key, $currentSite);

                }else // On connecte normalement l'utilisateur*/
                {
                    /** @var $currentToken OAuthToken */
                    $currentToken = $this->container->get('security.context')->getToken();
                    $currentUser  = $currentToken->getUser();

                    $this->container->get('hwi_oauth.account.connector')->connect($currentUser, $userInformation);
                    if ($currentToken instanceof OAuthToken) {
                        // Update user token with new details
                        $this->authenticateUser($request, $currentUser, $service, $currentToken->getRawToken(), false);
                    }else if ($currentToken instanceof UsernamePasswordToken) {
                        // Update user token with new details
                        $this->authenticateBasicUser($currentUser);
                    }
                }

                return $this->container->get('templating')->renderResponse('HWIOAuthBundle:Connect:connect_success.html.' . $this->getTemplatingEngine(), [
                    'userInformation' => $userInformation,
                ]);
            }
        }

        return $this->container->get('templating')->renderResponse('HWIOAuthBundle:Connect:connect_confirm.html.' . $this->getTemplatingEngine(), [
            'key'             => $key,
            'service'         => $service,
            'form'            => $form->createView(),
            'userInformation' => $userInformation,
        ]);
    }


    /**
     * Authenticate a user with Symfony Security
     *
     * @param UserInterface $user
     * @param string        $resourceOwnerName
     * @param string        $accessToken
     * @param boolean       $fakeLogin
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
        $userManager->refreshUser($user);
        $this->container->get('security.context')->getToken()->setAuthenticated(false);
    }
}
