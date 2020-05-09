<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Security;

use Exception;
use App\Entity\User;
use App\OAuth\TwitterOAuth;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @Route("/login-social")
 */
class LoginSocialController extends AbstractController
{
    /**
     * @Route("/check-{service<%patterns.social%>}", name="login_social_check", methods={"GET", "POST"})
     */
    public function connectCheck(): Response
    {
        throw new Exception('This code should not be reach!');
    }

    /**
     * @Route("/{service<%patterns.social%>}", name="login_social_start", methods={"GET", "POST"})
     */
    public function connect(string $service, ClientRegistry $clientRegistry, TwitterOAuth $twitterOAuth): Response
    {
        switch ($service) {
            case 'facebook':
                $scopes = ['public_profile', 'email'];
                break;
            case 'google':
                $scopes = ['email', 'profile'];
                break;
            case 'twitter':
                return $twitterOAuth->redirect(
                    $this->generateUrl('login_social_check', [
                        'service' => $service,
                    ], UrlGeneratorInterface::ABSOLUTE_URL)
                );
            default:
                $scopes = [];
                break;
        }

        return $clientRegistry
            ->getClient($service)
            ->redirect($scopes, []);
    }

    /**
     * @Route("/success-{service<%patterns.social%>}", name="login_social_success", methods={"GET"})
     */
    public function success(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (null === $user) {
            throw $this->createNotFoundException();
        }

        return $this->render('security/connect_success.html.twig', [
            'userInformation' => [
                'name' => $user->getUsername(),
                'email' => $user->getEmail(),
            ],
        ]);
    }
}
