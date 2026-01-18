<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\FacebookUser;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Smolblog\OAuth2\Client\Provider\TwitterUser;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final readonly class OAuthDataProvider
{
    public function __construct(private ClientRegistry $clientRegistry)
    {
    }

    public function getDatasFromToken(string $serviceName, AccessToken $token): array
    {
        $datas = [
            'accessToken' => $token->getToken(),
            'refreshToken' => $token->getRefreshToken(),
            'expires' => $token->getExpires(),
        ];

        $client = $this->clientRegistry->getClient($serviceName);
        $user = $client->fetchUserFromToken($token);

        match (true) {
            $user instanceof FacebookUser => $datas += [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'profilePicture' => $user->isDefaultPicture() ? null : $user->getPictureUrl(),
                'realname' => $user->getName(),
            ],
            $user instanceof GoogleUser => $datas += [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'profilePicture' => $user->getAvatar(),
                'realname' => $user->getName(),
            ],
            $user instanceof TwitterUser => $datas += [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'profilePicture' => $user->getImageUrl(),
                'realname' => $user->getName(),
                'nickname' => $user->getUsername(),
            ],
            default => throw new AuthenticationException(\sprintf('Unable to guess how to find user for service "%s"', $serviceName)),
        };

        // So ugly...
        if (empty($datas['email'])) {
            $datas['email'] = \sprintf('john.doe-%s@by-night.fr', uniqid('', true));
        }

        return $datas;
    }
}
