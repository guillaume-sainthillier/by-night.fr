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
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * ConnectController.
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
     * @throws NotFoundHttpException if `connect` functionality was not enabled
     * @throws AccessDeniedException if no user is authenticated
     *
     * @return Response
     */
    public function connectServiceAction(Request $request, $service)
    {
        $connect = $this->container->getParameter('hwi_oauth.connect');
        if (!$connect) {
            throw new NotFoundHttpException();
        }

        $hasUser = $this->isGranted('IS_AUTHENTICATED_REMEMBERED');
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
                $this->container->get('hwi_oauth.security.oauth_utils')->getServiceAuthUrl($request, $resourceOwner)
            );

            // save in session
            $session->set('_hwi_oauth.connect_confirmation.'.$key, $accessToken);
        } else {
            $accessToken = $session->get('_hwi_oauth.connect_confirmation.'.$key);
        }

        // Redirect to the login path if the token is empty (Eg. User cancelled auth)
        if (null === $accessToken) {
            return $this->redirectToRoute($this->container->getParameter('hwi_oauth.failed_auth_path'));
        }

        $userInformation = $resourceOwner->getUserInformation($accessToken);

        // Show confirmation page?
        if (!$this->container->getParameter('hwi_oauth.connect.confirmation')) {
            goto show_confirmation_page;
        }

        // Symfony <3.0 BC
        /** @var $form FormInterface */
        $form = method_exists('Symfony\Component\Form\AbstractType', 'getBlockPrefix')
            ? $this->createForm('Symfony\Component\Form\Extension\Core\Type\FormType')
            : $this->createForm('form');
        // Handle the form
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            show_confirmation_page:

            $session = $this->container->get('session');
            if ($session->has('connect_site')) { // On veut connecter le site et non l'utilisateur
                $session->remove('connect_site');
                $siteManager = $this->container->get('site_manager');
                $currentSite = $siteManager->getCurrentSite();

                $this->container->get('hwi_oauth.account.connector')->connectSite($userInformation);

                $em = $this->container->get('doctrine.orm.entity_manager');

                $em->persist($currentSite);
                $em->flush();
            } else { // On connecte normalement l'utilisateur*/
                /** @var $currentToken OAuthToken */
                $currentToken = $this->get('security.token_storage')->getToken();
                $currentUser = $currentToken->getUser();

                $this->container->get('hwi_oauth.account.connector')->connect($currentUser, $userInformation);

                if ($currentToken instanceof OAuthToken) {
                    // Update user token with new details
                    $newToken =
                        is_array($accessToken) &&
                        (isset($accessToken['access_token']) || isset($accessToken['oauth_token'])) ?
                            $accessToken : $currentToken->getRawToken();

                    $this->authenticateUser($request, $currentUser, $service, $newToken, false);
                } else {
                    $this->refreshUser($currentUser);
                }

                if ($targetPath = $this->getTargetPath($session)) {
                    return $this->redirect($targetPath);
                }

                return $this->container->get('templating')->renderResponse('HWIOAuthBundle:Connect:connect_success.html.'.$this->getTemplatingEngine(), [
                    'userInformation' => $userInformation,
                    'service'         => $service,
                ]);
            }

            return $this->container->get('templating')->renderResponse('HWIOAuthBundle:Connect:connect_success.html.'.$this->getTemplatingEngine(), [
                'userInformation' => $userInformation,
            ]);
        }

        return $this->container->get('templating')->renderResponse('HWIOAuthBundle:Connect:connect_confirm.html.'.$this->getTemplatingEngine(), [
            'key'             => $key,
            'service'         => $service,
            'form'            => $form->createView(),
            'userInformation' => $userInformation,
        ]);
    }

    /**
     * @param SessionInterface $session
     *
     * @return string|null
     */
    private function getTargetPath(SessionInterface $session)
    {
        foreach ($this->container->getParameter('hwi_oauth.firewall_names') as $providerKey) {
            $sessionKey = '_security.'.$providerKey.'.target_path';
            if ($session->has($sessionKey)) {
                return $session->get($sessionKey);
            }
        }
    }

    protected function refreshUser(UserInterface $user)
    {
        $userManager = $this->container->get('fos_user.user_manager');
        $userManager->reloadUser($user);
        $this->container->get('security.token_storage')->getToken()->setAuthenticated(false);
    }
}
