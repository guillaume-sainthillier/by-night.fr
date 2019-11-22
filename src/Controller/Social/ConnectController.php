<?php

namespace App\Controller\Social;

use Exception;
use FOS\UserBundle\Model\UserManagerInterface;
use HWI\Bundle\OAuthBundle\Controller\ConnectController as BaseController;
use HWI\Bundle\OAuthBundle\Event\FilterUserResponseEvent;
use HWI\Bundle\OAuthBundle\Event\GetResponseUserEvent;
use HWI\Bundle\OAuthBundle\HWIOAuthEvents;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwnerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
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
     * @Route("/service/{service}", name="hwi_oauth_connect_service")
     * Connects a user to a given account if the user is logged in and connect is enabled.
     *
     * @param Request $request the active request
     * @param string $service name of the resource owner to connect to
     *
     * @return Response
     *
     * @throws Exception
     * @throws NotFoundHttpException if `connect` functionality was not enabled
     * @throws AccessDeniedException if no user is authenticated
     */
    public function connectServiceAction(Request $request, $service)
    {
        $connect = $this->container->getParameter('hwi_oauth.connect');
        if (!$connect) {
            throw new NotFoundHttpException();
        }

        $hasUser = $this->isGranted($this->container->getParameter('hwi_oauth.grant_rule'));
        if (!$hasUser) {
            throw new AccessDeniedException('Cannot connect an account.');
        }

        // Get the data from the resource owner
        $resourceOwner = $this->getResourceOwnerByName($service);

        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $key = $request->query->get('key', time());

        if ($resourceOwner->handles($request)) {
            $accessToken = $resourceOwner->getAccessToken(
                $request,
                $this->container->get('hwi_oauth.security.oauth_utils')->getServiceAuthUrl($request, $resourceOwner)
            );

            // save in session
            $session->set('_hwi_oauth.connect_confirmation.' . $key, $accessToken);
        } else {
            $accessToken = $session->get('_hwi_oauth.connect_confirmation.' . $key);
        }

        // Redirect to the login path if the token is empty (Eg. User cancelled auth)
        if (null === $accessToken) {
            if ($this->container->getParameter('hwi_oauth.failed_use_referer') && $targetPath = $this->getTargetPath($session, 'failed_target_path')) {
                return $this->redirect($targetPath);
            }

            return $this->redirectToRoute($this->container->getParameter('hwi_oauth.failed_auth_path'));
        }

        $userInformation = $resourceOwner->getUserInformation($accessToken);

        // Show confirmation page?
        if (!$this->container->getParameter('hwi_oauth.connect.confirmation')) {
            if ($session->has('connect_site')) { // On veut connecter le site et non l'utilisateur
                $session->remove('connect_site');
                $this->container->get('hwi_oauth.account.connector')->connectSite($userInformation);

                $em = $this->getDoctrine()->getManager();
                $em->flush();

                return $this->render('@HWIOAuth/Connect/connect_success.html.twig', [
                    'userInformation' => $userInformation,
                    'service' => $service,
                ]);
            }
            return $this->getConfirmationResponse($request, $accessToken, $service);
        }

        // Symfony <3.0 BC
        /** @var $form \Symfony\Component\Form\FormInterface */
        $form = method_exists('Symfony\Component\Form\AbstractType', 'getBlockPrefix')
            ? $this->createForm(FormType::class)
            : $this->createForm('form');
        // Handle the form
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // On connecte normalement l'utilisateur
            return $this->getConfirmationResponse($request, $accessToken, $service);
        }

        $event = new GetResponseUserEvent($this->getUser(), $request);
        $this->get('event_dispatcher')->dispatch(HWIOAuthEvents::CONNECT_INITIALIZE, $event);

        if ($response = $event->getResponse()) {
            return $response;
        }

        return $this->render('@HWIOAuth/Connect/connect_confirm.html.twig', [
            'key' => $key,
            'service' => $service,
            'form' => $form->createView(),
            'userInformation' => $resourceOwner->getUserInformation($accessToken),
        ]);
    }

    /**
     * @return string|null
     */
    private function getTargetPath(SessionInterface $session)
    {
        foreach ($this->container->getParameter('hwi_oauth.firewall_names') as $providerKey) {
            $sessionKey = '_security.' . $providerKey . '.target_path';
            if ($session->has($sessionKey)) {
                return $session->get($sessionKey);
            }
        }

        return null;
    }

    /**
     * @param Request $request The active request
     * @param array $accessToken The access token
     * @param string $service Name of the resource owner to connect to
     *
     * @return Response
     *
     * @throws NotFoundHttpException if there is no resource owner with the given name
     */
    private function getConfirmationResponse(Request $request, array $accessToken, $service)
    {
        /** @var $currentToken OAuthToken */
        $currentToken = $this->container->get('security.token_storage')->getToken();
        /** @var $currentUser UserInterface */
        $currentUser = $currentToken->getUser();

        /** @var $resourceOwner ResourceOwnerInterface */
        $resourceOwner = $this->getResourceOwnerByName($service);
        /** @var $userInformation UserResponseInterface */
        $userInformation = $resourceOwner->getUserInformation($accessToken);

        $event = new GetResponseUserEvent($currentUser, $request);
        $this->get('event_dispatcher')->dispatch(HWIOAuthEvents::CONNECT_CONFIRMED, $event);

        $this->container->get('hwi_oauth.account.connector')->connect($currentUser, $userInformation);

        if ($currentToken instanceof OAuthToken) {
            // Update user token with new details
            $newToken =
                \is_array($accessToken) &&
                (isset($accessToken['access_token']) || isset($accessToken['oauth_token'])) ?
                    $accessToken : $currentToken->getRawToken();

            $this->authenticateUser($request, $currentUser, $service, $newToken, false);
        }

        if (null === $response = $event->getResponse()) {
            if ($targetPath = $this->getTargetPath($request->getSession())) {
                $response = $this->redirect($targetPath);
            } else {
                $response = $this->render('@HWIOAuth/Connect/connect_success.html.twig', [
                    'userInformation' => $userInformation,
                    'service' => $service,
                ]);
            }
        }

        $event = new FilterUserResponseEvent($currentUser, $request, $response);
        $this->get('event_dispatcher')->dispatch(HWIOAuthEvents::CONNECT_COMPLETED, $event);

        return $response;
    }

    protected function refreshUser(UserInterface $user)
    {
        $userManager = $this->container->get(UserManagerInterface::class);
        $userManager->reloadUser($user);
        $this->get('security.token_storage')->getToken()->setAuthenticated(false);
    }
}
