<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager\Security;

use Override;
use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface;

/**
 * The authenticator app being set up, apart from the User: scheb builds its QR code and checks its codes from it, while
 * the User keeps their current secret until the first code is confirmed. A flush in the meantime saves nothing.
 */
final readonly class PendingTotp implements TwoFactorInterface
{
    public function __construct(
        private string $username,
        private string $secret,
    ) {
    }

    #[Override]
    public function isGoogleAuthenticatorEnabled(): bool
    {
        return true;
    }

    #[Override]
    public function getGoogleAuthenticatorUsername(): string
    {
        return $this->username;
    }

    #[Override]
    public function getGoogleAuthenticatorSecret(): string
    {
        return $this->secret;
    }
}
