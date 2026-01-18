<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Security;

use App\Controller\AbstractController;
use App\Entity\User;
use Exception;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/login-social')]
final class LoginSocialController extends AbstractController
{
    #[Route(path: '/check-{service<%patterns.social%>}', name: 'login_social_check', methods: ['GET', 'POST'])]
    public function connectCheck(): never
    {
        throw new Exception('This code should not be reach!');
    }

    #[Route(path: '/{service<%patterns.social%>}', name: 'login_social_start', methods: ['GET', 'POST'])]
    public function connect(string $service, ClientRegistry $clientRegistry): Response
    {
        $scopes = match ($service) {
            'facebook' => ['public_profile', 'email'],
            'google' => ['email', 'profile'],
            'twitter' => ['users.email', 'users.read', 'tweet.read', 'offline.access'],
            default => [],
        };

        return $clientRegistry
            ->getClient($service)
            ->redirect($scopes, []);
    }

    #[Route(path: '/success-{service<%patterns.social%>}', name: 'login_social_success', methods: ['GET'])]
    public function success(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (null === $user) {
            throw $this->createNotFoundException();
        }

        return $this->render('security/connect-success.html.twig', [
            'userInformation' => [
                'name' => $user->getUserIdentifier(),
                'email' => $user->getEmail(),
            ],
        ]);
    }
}
