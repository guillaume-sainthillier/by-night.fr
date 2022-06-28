<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\App\SocialManager;
use App\OAuth\TwitterOAuth;
use App\Security\OAuthDataProvider;
use App\Social\Social;
use App\Social\SocialProvider;
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
     * @Route("/check-{service<%patterns.admin_social%>}", name="admin_login_social_check", methods={"GET", "POST"})
     */
    public function connectCheck(string $service, Social $social, SocialManager $socialManager, ClientRegistry $clientRegistry, OAuthDataProvider $OAuthDataProvider, TwitterOAuth $twitterOAuth): Response
    {
        if (SocialProvider::TWITTER_ADMIN === $service) {
            $accessToken = $twitterOAuth->getAccessToken();
        } else {
            $client = $clientRegistry->getClient($service);
            $accessToken = $client->getAccessToken();
        }

        $datas = $OAuthDataProvider->getDatasFromToken($service, $accessToken);
        $appOAuth = $socialManager->getAppOAuth();
        $social->connectSite($appOAuth, $datas);
        $this->getDoctrine()->getManager()->flush();

        return $this->render('security/connect-success.html.twig', [
            'userInformation' => [
                'name' => $datas['realname'],
                'email' => $datas['email'],
            ],
        ]);
    }

    /**
     * @Route("/{service<%patterns.admin_social%>}", name="admin_login_social_start", methods={"GET"})
     */
    public function connect(string $service, ClientRegistry $clientRegistry, TwitterOAuth $twitterOAuth): Response
    {
        switch ($service) {
            case 'facebook_admin':
                $scopes = ['public_profile', 'email', 'pages_show_list', 'manage_pages'];
                break;
            case 'twitter_admin':
                return $twitterOAuth->redirect(
                    $this->generateUrl('admin_login_social_check', [
                        'service' => $service,
                    ], UrlGeneratorInterface::ABSOLUTE_URL)
                );
            default:
                $scopes = [];
                break;
        }

        return $clientRegistry
            ->getClient($service)
            ->redirect($scopes, [
                'redirect_uri' => $this->generateUrl('admin_login_social_check', [
                    'service' => $service,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
    }
}
