<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Social;

use Facebook\Facebook as Client;

class Facebook extends Social
{
    protected Client $client;

    protected function constructClient()
    {
        $this->client = new Client([
            'app_id' => $this->id,
            'app_secret' => $this->secret,
        ]);
    }

    public function getInfoPropertyPrefix(): ?string
    {
        return 'facebook';
    }

    protected function getRoleName(): string
    {
        return 'ROLE_FACEBOOK';
    }
}
