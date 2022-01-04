<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\OAuth;

use Abraham\TwitterOAuth\TwitterOAuth as BaseClient;
use Abraham\TwitterOAuth\TwitterOAuthException;
use KnpU\OAuth2ClientBundle\Exception\InvalidStateException;
use KnpU\OAuth2ClientBundle\Exception\MissingAuthorizationCodeException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class TwitterOAuth
{
    private string $clientId;
    private string $clientSecret;

    private RequestStack $requestStack;

    private const OAUTH_TOKEN_SESSION_KEY = '_oauth_token';
    private const OAUTH_TOKEN_SECRET_SESSION_KEY = '_oauth_token_secret';

    public function __construct(string $clientId, string $clientSecret, RequestStack $requestStack)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->requestStack = $requestStack;
    }

    public function redirect(string $redirectUri): RedirectResponse
    {
        $client = new BaseClient($this->clientId, $this->clientSecret);
        $request_token = $client->oauth('oauth/request_token', ['oauth_callback' => $redirectUri]);

        $session = $this->getCurrentSession();
        $session->set(self::OAUTH_TOKEN_SESSION_KEY, $request_token['oauth_token']);
        $session->set(self::OAUTH_TOKEN_SECRET_SESSION_KEY, $request_token['oauth_token_secret']);

        $url = $client->url('oauth/authenticate', ['oauth_token' => $request_token['oauth_token']]);

        return new RedirectResponse($url);
    }

    public function getAccessToken(): TwitterAccessToken
    {
        $session = $this->getCurrentSession();
        if (!$session->has(self::OAUTH_TOKEN_SECRET_SESSION_KEY) || !$session->has(self::OAUTH_TOKEN_SESSION_KEY)) {
            throw new InvalidStateException('Invalid state');
        }

        $oauthToken = $session->get(self::OAUTH_TOKEN_SESSION_KEY);
        $oauthTokenSecret = $session->get(self::OAUTH_TOKEN_SECRET_SESSION_KEY);

        $request = $this->requestStack->getCurrentRequest();
        if (!$request->query->has('oauth_token') && !$request->query->has('oauth_verifier')) {
            throw new MissingAuthorizationCodeException('No "code" parameter was found (usually this is a query parameter)!');
        }

        $givenOauthToken = $request->query->get('oauth_token');
        $givenOauthVerifier = $request->query->get('oauth_verifier');

        if ($givenOauthToken !== $oauthToken) {
            throw new InvalidStateException('States don\'t match');
        }

        $client = new BaseClient($this->clientId, $this->clientSecret, $givenOauthToken, $oauthTokenSecret);

        try {
            $access_token = $client->oauth('oauth/access_token', ['oauth_verifier' => $givenOauthVerifier]);
        } catch (TwitterOAuthException $exception) {
            throw new InvalidStateException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return new TwitterAccessToken([
            'resource_owner_id' => 'twitter',
            'access_token' => $access_token['oauth_token'],
            'oauth_token_secret' => $access_token['oauth_token_secret'],
        ]);
    }

    public function fetchUserFromToken(TwitterAccessToken $token): ResourceOwnerInterface
    {
        $client = new BaseClient($this->clientId, $this->clientSecret, $token->getToken(), $token->getTokenSecret());
        $content = $client->get('account/verify_credentials', ['include_email' => true]);

        return new TwitterUser(json_decode(json_encode($content, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));
    }

    private function getCurrentSession(): SessionInterface
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session;
    }
}
