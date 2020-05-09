<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security;

use App\Entity\User;
use App\OAuth\TwitterOAuth;
use App\Repository\UserRepository;
use App\Social\SocialProvider;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Exception\InvalidStateException;
use KnpU\OAuth2ClientBundle\Exception\MissingAuthorizationCodeException;
use KnpU\OAuth2ClientBundle\Security\Authenticator\SocialAuthenticator;
use KnpU\OAuth2ClientBundle\Security\Exception\InvalidStateAuthenticationException;
use KnpU\OAuth2ClientBundle\Security\Exception\NoAuthCodeAuthenticationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserSocialAuthenticator extends SocialAuthenticator
{
    private Security $security;

    private ClientRegistry $clientRegistry;

    private EntityManagerInterface $em;

    private UrlGeneratorInterface $router;

    private SocialProvider $socialProvider;

    private UserRepository $userRepository;
    private OAuthDataProvider $oAuthDataProvider;
    private TwitterOAuth $twitterOAuth;

    public function __construct(Security $security, ClientRegistry $clientRegistry, EntityManagerInterface $em, UrlGeneratorInterface $router, SocialProvider $socialProvider, OAuthDataProvider $oAuthDataProvider, TwitterOAuth $twitterOAuth, UserRepository $userRepository)
    {
        $this->security = $security;
        $this->clientRegistry = $clientRegistry;
        $this->em = $em;
        $this->router = $router;
        $this->socialProvider = $socialProvider;
        $this->userRepository = $userRepository;
        $this->oAuthDataProvider = $oAuthDataProvider;
        $this->twitterOAuth = $twitterOAuth;
    }

    public function supports(Request $request)
    {
        return 'login_social_check' === $request->attributes->get('_route');
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $service = $credentials['service'];
        $token = $credentials['token'];
        $social = $this->socialProvider->getSocial($service);
        $datas = $this->oAuthDataProvider->getDatasFromToken($service, $token);

        //In case of adding new socials in profile
        if (null !== $this->security->getUser()) {
            /** @var User $existingUser */
            $existingUser = $this->security->getUser();
        } else {
            $existingUser = $this
                ->userRepository
                ->findOneBySocial($datas['email'], $social->getInfoPropertyPrefix(), $datas['id']);
        }

        if ($existingUser === null) {
            $existingUser = new User();
            $existingUser
                ->setUsername($datas['realname'] ?: $datas['email'] ?: $datas['id'])
                ->setPassword('notused')
                ->setFromLogin(false)
                ->setEnabled(true)
                ->setEmail($datas['email']);

            //Avoir duplicate exception
            $initialUsername = $existingUser->getUsername();
            for ($i = 1;; ++$i) {
                $username = $existingUser->getUsername();
                $encoding = mb_detect_encoding($username);
                $usernameCanonical = $encoding
                    ? mb_convert_case($username, \MB_CASE_LOWER, $encoding)
                    : mb_convert_case($username, \MB_CASE_LOWER);
                $existingUser->setUsernameCanonical($usernameCanonical);
                $persistedUser = $this->userRepository->findOneBy(['usernameCanonical' => $usernameCanonical]);
                if (null === $persistedUser) {
                    break;
                }
                $existingUser->setUsername(sprintf('%s-%d', $initialUsername, $i));
            }

            $this->em->persist($existingUser);
        }

        if (!$existingUser->getFirstname() && $datas['firstName']) {
            $existingUser->setFirstname($datas['firstName']);
        }

        if (!$existingUser->getLastname() && $datas['lastName']) {
            $existingUser->setLastname($datas['lastName']);
        }

        $social->connectUser($existingUser, $datas);
        $this->em->flush();

        return $existingUser;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        return new RedirectResponse(
            $this->router->generate('login_social_success', [
                'service' => $request->attributes->get('service'),
            ])
        );
    }

    public function getCredentials(Request $request)
    {
        $service = $request->attributes->get('service');

        //Still use Oauth 1.0...
        if ($service === SocialProvider::TWITTER) {
            return [
                'service' => $service,
                'token' => $this->fetchTwitterAccessToken(),
            ];
        }
        $client = $this->clientRegistry->getClient($service);

        return [
            'service' => $service,
            'token' => $this->fetchAccessToken($client),
        ];
    }

    private function fetchTwitterAccessToken()
    {
        try {
            return $this->twitterOAuth->getAccessToken();
        } catch (MissingAuthorizationCodeException $e) {
            throw new NoAuthCodeAuthenticationException();
        } catch (InvalidStateException $e) {
            throw new InvalidStateAuthenticationException($e);
        }
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse(
            $this->router->generate('fos_user_security_login'),
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }

    /**
     * Called when authentication is needed, but it's not sent.
     * This redirects to the 'login'.
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new RedirectResponse(
            $this->router->generate('fos_user_security_login'),
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }
}
