<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security;

use App\Entity\User;
use App\OAuth\TwitterAccessToken;
use App\OAuth\TwitterOAuth;
use App\Repository\UserRepository;
use App\Social\SocialProvider;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Exception\InvalidStateException;
use KnpU\OAuth2ClientBundle\Exception\MissingAuthorizationCodeException;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use KnpU\OAuth2ClientBundle\Security\Exception\InvalidStateAuthenticationException;
use KnpU\OAuth2ClientBundle\Security\Exception\NoAuthCodeAuthenticationException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class UserSocialAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly Security $security,
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $router,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly SocialProvider $socialProvider,
        private readonly OAuthDataProvider $oAuthDataProvider,
        private readonly TwitterOAuth $twitterOAuth,
        private readonly UserRepository $userRepository
    ) {
    }

    /**
     * Manual login
     */
    public function login(User $user, Request $request): ?Response
    {
        return $this->userAuthenticator->authenticateUser(
            $user,
            $this,
            $request
        );
    }

    public function supports(Request $request): ?bool
    {
        return 'login_social_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $service = $request->attributes->get('service');

        // Still use Oauth 1.0...
        if (SocialProvider::TWITTER === $service) {
            $accessToken = $this->fetchTwitterAccessToken();
        } else {
            $client = $this->clientRegistry->getClient($service);
            $accessToken = $this->fetchAccessToken($client);
        }

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $service) {
                $social = $this->socialProvider->getSocial($service);
                $datas = $this->oAuthDataProvider->getDatasFromToken($service, $accessToken);

                // In case of adding new socials in profile
                if (null !== $this->security->getUser()) {
                    /** @var User $existingUser */
                    $existingUser = $this->security->getUser();
                } else {
                    $existingUser = $this
                        ->userRepository
                        ->findOneBySocial($datas['email'], $social->getInfoPropertyPrefix(), $datas['id']);
                }

                if (null === $existingUser) {
                    $existingUser = new User();
                    $existingUser
                        ->setUsername(($datas['realname'] ?: $datas['email']) ?: $datas['id'])
                        ->setPassword('notused')
                        ->setFromLogin(false)
                        ->setVerified(true)
                        ->setEmail($datas['email']);

                    // Avoir duplicate exception
                    $initialUsername = $existingUser->getUserIdentifier();
                    for ($i = 1;; ++$i) {
                        $persistedUser = $this->userRepository->findOneBy(['username' => $existingUser->getUserIdentifier()]);
                        if (null === $persistedUser) {
                            break;
                        }

                        $existingUser->setUsername(sprintf('%s-%d', $initialUsername, $i));
                    }

                    $this->entityManager->persist($existingUser);
                }

                if (!$existingUser->getFirstname() && $datas['firstName']) {
                    $existingUser->setFirstname($datas['firstName']);
                }

                if (!$existingUser->getLastname() && $datas['lastName']) {
                    $existingUser->setLastname($datas['lastName']);
                }

                $social->connectUser($existingUser, $datas);
                $this->entityManager->flush();

                return $existingUser;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse(
            $this->router->generate('login_social_success', [
                'service' => $request->attributes->get('service'),
            ])
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse(
            $this->router->generate('app_login'),
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }

    private function fetchTwitterAccessToken(): TwitterAccessToken
    {
        try {
            return $this->twitterOAuth->getAccessToken();
        } catch (MissingAuthorizationCodeException) {
            throw new NoAuthCodeAuthenticationException();
        } catch (InvalidStateException $invalidStateException) {
            throw new InvalidStateAuthenticationException($invalidStateException);
        }
    }
}
