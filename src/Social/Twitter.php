<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Social;

use Override;

final class Twitter extends Social
{
    /**
     * {@inheritDoc}
     */
    protected function constructClient(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    protected function getInfoProperties(): array
    {
        return ['id', 'accessToken', 'refreshToken', 'expires', 'realname', 'nickname', 'email', 'profilePicture'];
    }

    /**
     * {@inheritDoc}
     */
    public function getInfoPropertyPrefix(): string
    {
        return 'twitter';
    }

    /**
     * {@inheritDoc}
     */
    protected function getRoleName(): string
    {
        return 'ROLE_TWITTER';
    }
}
