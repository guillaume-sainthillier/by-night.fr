<?php

namespace TBN\UserBundle\Handler;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use TBN\MainBundle\Site\SiteManager;

class AuthenticationHandler implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface
{
    protected $translator;

    protected $router;

    protected $session;

    protected $site_manager;

    public function __construct($translator, $router, $session, SiteManager $site_manager)
    {
        $this->translator   = $translator;
        $this->router       = $router;
        $this->session      = $session;
        $this->site_manager = $site_manager;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        if ($request->isXmlHttpRequest()) {
            $result = ['success' => true];

            return new JsonResponse($result);
        } else {
            $key = '_security.main.target_path'; //where "main" is your firewall name

            if (($targetPath = $request->getSession()->get($key))) {
                $url = $targetPath;
            } else {
                //check if the referer session key has been set
                if ($this->session->has($key)) {
                    //set the url based on the link they were trying to access before being authenticated
                    $url = $this->session->get($key);

                    //remove the session key
                    $this->session->remove($key);
                } else {
                    $user      = $token->getUser();
                    $subdomain = $user->getSite()->getSubdomain();
                    $url       = $this->router->generate('tbn_agenda_index', ['subdomain' => $subdomain]);
                }
            }

            return new RedirectResponse($url);
        }
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        if ($request->isXmlHttpRequest()) {
            $result = [
                'success' => false,
                'message' => $this->translator->trans($exception->getMessage(), [], 'FOSUserBundle'),
            ];

            return new JsonResponse($result);
        } else {
            // Handle non XmlHttp request here
            $request->getSession()->getFlashBag()->set('error', $exception->getMessage());
            $url = $this->router->generate('fos_user_security_login');

            return new RedirectResponse($url);
        }
    }
}
