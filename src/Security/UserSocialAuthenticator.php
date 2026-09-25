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
use App\Repository\UserRepository;
use App\Social\SocialProvider;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
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

final class UserSocialAuthenticator extends OAuth2Authenticator
{
    /**
     * The name of a newcomer the network gives no name of: the username is public (profile
     * URL, comments, search), the e-mail never stands in for it.
     */
    private const string DEFAULT_USERNAME = 'Membre';

    public function __construct(
        private readonly Security $security,
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $router,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly SocialProvider $socialProvider,
        private readonly OAuthDataProvider $oAuthDataProvider,
        private readonly UserRepository $userRepository,
        private readonly UnverifiedAccountClaim $unverifiedAccountClaim,
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

    public function supports(Request $request): bool
    {
        return 'login_social_check' === $request->attributes->getString('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $service = $request->attributes->getString('service');
        $client = $this->clientRegistry->getClient($service);
        $accessToken = $this->fetchAccessToken($client);

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

                    if (null !== $existingUser) {
                        $this->unverifiedAccountClaim->claim($existingUser, $social, (string) $datas['id']);
                    }
                }

                if (null === $existingUser) {
                    $existingUser = new User();
                    $existingUser
                        ->setUsername($this->getFreeUsername($datas['realname'] ?: ($datas['nickname'] ?? null) ?: self::DEFAULT_USERNAME))
                        ->setPassword('notused')
                        ->setFromLogin(false)
                        ->setVerified(true)
                        ->setEmail($datas['email']);

                    $this->entityManager->persist($existingUser);
                }

                // Twitter gives neither
                if (!$existingUser->getFirstname() && ($datas['firstName'] ?? null)) {
                    $existingUser->setFirstname($datas['firstName']);
                }

                if (!$existingUser->getLastname() && ($datas['lastName'] ?? null)) {
                    $existingUser->setLastname($datas['lastName']);
                }

                $social->connectUser($existingUser, $datas);
                $this->entityManager->flush();

                return $existingUser;
            })
        );
    }

    /**
     * The name, or the name followed by the first free number ("Camille Martin-2"): the
     * username is unique. getUserIdentifier() is the e-mail, not the username.
     */
    private function getFreeUsername(string $name): string
    {
        $username = $name;
        for ($i = 1; null !== $this->userRepository->findOneBy(['username' => $username]); ++$i) {
            $username = \sprintf('%s-%d', $name, $i);
        }

        return $username;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new RedirectResponse(
            $this->router->generate('login_social_success', [
                'service' => $request->attributes->getString('service'),
            ])
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse(
            $this->router->generate('app_login'),
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }
}
