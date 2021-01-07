<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\OAuth;

use League\OAuth2\Client\Token\AccessToken;

class TwitterAccessToken extends AccessToken
{
    public function getTokenSecret(): ?string
    {
        return $this->values['oauth_token_secret'] ?? null;
    }
}
