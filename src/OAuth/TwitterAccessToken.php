<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\OAuth;

use League\OAuth2\Client\Token\AccessToken;

final class TwitterAccessToken extends AccessToken
{
    public function getTokenSecret(): ?string
    {
        return $this->values['oauth_token_secret'] ?? null;
    }
}
