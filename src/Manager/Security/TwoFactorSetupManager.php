<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager\Security;

use App\Entity\User;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The state of a 2FA setup, kept in the session until it is confirmed: the secret of the authenticator app and the
 * recovery codes are generated once, and shown again on each visit until the user confirms them. Nothing is set on the
 * User before: the caller does it, and flushes.
 */
final readonly class TwoFactorSetupManager
{
    public const string SESSION_SECRET_KEY = '_2fa_google_authenticator_secret';

    public const string SESSION_CODES_KEY = '_2fa_recovery_codes';

    public const int RECOVERY_CODE_COUNT = 10;

    /** Without the look-alikes (0/O, 1/I/L): the codes are copied by hand from paper */
    private const string RECOVERY_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(
        private RequestStack $requestStack,
        private GoogleAuthenticatorInterface $googleAuthenticator,
    ) {
    }

    public function getOrGenerateSecret(): string
    {
        $session = $this->requestStack->getSession();
        $secret = $session->get(self::SESSION_SECRET_KEY);
        if (!\is_string($secret) || '' === $secret) {
            $secret = $this->googleAuthenticator->generateSecret();
            $session->set(self::SESSION_SECRET_KEY, $secret);
        }

        return $secret;
    }

    /**
     * The authenticator app being set up by the user, for its QR code and the check of its first code
     */
    public function getPendingTotp(User $user): PendingTotp
    {
        return new PendingTotp($user->getGoogleAuthenticatorUsername(), $this->getOrGenerateSecret());
    }

    public function clearSecret(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_SECRET_KEY);
    }

    /**
     * @return list<string>
     */
    public function getOrGenerateRecoveryCodes(): array
    {
        $session = $this->requestStack->getSession();
        $codes = $session->get(self::SESSION_CODES_KEY);
        if (\is_array($codes) && [] !== $codes) {
            return array_values(array_map(strval(...), $codes));
        }

        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; ++$i) {
            $codes[] = $this->randomChunk() . '-' . $this->randomChunk();
        }

        $session->set(self::SESSION_CODES_KEY, $codes);

        return $codes;
    }

    public function hasPendingRecoveryCodes(): bool
    {
        return $this->requestStack->getSession()->has(self::SESSION_CODES_KEY);
    }

    public function clearRecoveryCodes(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_CODES_KEY);
    }

    private function randomChunk(): string
    {
        $chunk = '';
        for ($i = 0; $i < 4; ++$i) {
            $chunk .= self::RECOVERY_CODE_ALPHABET[random_int(0, \strlen(self::RECOVERY_CODE_ALPHABET) - 1)];
        }

        return $chunk;
    }
}
