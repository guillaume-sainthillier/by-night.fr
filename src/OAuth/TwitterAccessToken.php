<?php

namespace App\OAuth;

use League\OAuth2\Client\Token\AccessToken;

class TwitterAccessToken extends AccessToken
{
    public function getTokenSecret(): ?string
    {
        return $this->values['oauth_token_secret'] ?? null;
    }
}