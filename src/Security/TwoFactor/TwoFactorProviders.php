<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\TwoFactor;

/**
 * The aliases of the scheb/2fa-bundle providers a member may turn on
 */
final class TwoFactorProviders
{
    /** A code of an authenticator app */
    public const string TOTP = 'google';

    /** A passkey (jbtronics/2fa-webauthn) */
    public const string WEBAUTHN = 'webauthn_two_factor_provider';
}
