<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\App\SocialManager;
use App\Controller\AbstractController;
use App\Security\OAuthDataProvider;
use App\Social\Social;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(path: '/login-social')]
final class LoginSocialController extends AbstractController
{
    #[Route(path: '/check-{service<%patterns.admin_social%>}', name: 'admin_login_social_check', methods: ['GET', 'POST'])]
    public function connectCheck(string $service, Social $social, SocialManager $socialManager, ClientRegistry $clientRegistry, OAuthDataProvider $OAuthDataProvider): Response
    {
        $client = $clientRegistry->getClient($service);
        $accessToken = $client->getAccessToken();

        $datas = $OAuthDataProvider->getDatasFromToken($service, $accessToken);
        $appOAuth = $socialManager->getAppOAuth();
        $social->connectSite($appOAuth, $datas);
        $this->getEntityManager()->flush();

        return $this->render('security/connect-success.html.twig', [
            'userInformation' => [
                'name' => $datas['realname'],
                'email' => $datas['email'],
            ],
        ]);
    }

    #[Route(path: '/{service<%patterns.admin_social%>}', name: 'admin_login_social_start', methods: ['GET'])]
    public function connect(string $service, ClientRegistry $clientRegistry): Response
    {
        $scopes = match ($service) {
            'facebook_admin' => ['public_profile', 'email', 'pages_show_list', 'manage_pages'],
            'twitter_admin' => ['users.read', 'tweet.read', 'offline.access'],
            default => [],
        };

        return $clientRegistry
            ->getClient($service)
            ->redirect($scopes, [
                'redirect_uri' => $this->generateUrl('admin_login_social_check', [
                    'service' => $service,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
    }
}
