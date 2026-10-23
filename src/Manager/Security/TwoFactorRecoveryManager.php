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
use App\Entity\WebauthnKey;
use App\Manager\MailerManager;
use DateInterval;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Self-service recovery of a lost second factor, by a link e-mailed to the user.
 *
 * The link is signed over the user id, their password hash and their current second factors: nothing is stored, yet a
 * link works once, since the recovery clears these factors. Setting them up again or changing the password kills the
 * links issued before too. A user without any second factor left is refused outright.
 */
final readonly class TwoFactorRecoveryManager
{
    public function __construct(
        private MailerManager $mailerManager,
        private SignedUrlManager $signedUrlManager,
        private ClockInterface $clock,
    ) {
    }

    public function sendRecoveryLink(User $user): void
    {
        // A member a social network logged in without an address has nowhere to receive it
        if (null === $user->getEmail()) {
            return;
        }

        $url = $this->signedUrlManager->generate('app_two_factor_recovery', ['id' => $user->getId()], $this->getLinkState($user));
        $expiresAt = $this->clock->now()->add(new DateInterval(SignedUrlManager::LIFETIME));

        $this->mailerManager->sendTwoFactorRecoveryLink($user, $url, $expiresAt);
    }

    public function isLinkValid(Request $request, User $user): bool
    {
        return ($user->hasSecondFactor() || $user->isBackupCodeEnabled())
            && $this->signedUrlManager->isValid($request, $this->getLinkState($user));
    }

    /**
     * Turns every second factor off (the caller flushes) and tells the user by e-mail
     */
    public function reset(User $user): void
    {
        $user->setGoogleAuthenticatorSecret(null);
        $user->setBackupCodes(null);
        foreach ($user->getWebauthnKeys()->toArray() as $key) {
            $user->removeWebauthnKey($key);
        }

        $this->mailerManager->sendTwoFactorReset($user);
    }

    /**
     * @return list<mixed>
     */
    private function getLinkState(User $user): array
    {
        $keyIds = $user->getWebauthnKeys()->map(static fn (WebauthnKey $key): ?int => $key->getId())->getValues();
        sort($keyIds);

        return [
            $user->getId(),
            $user->getPassword(),
            $user->getGoogleAuthenticatorSecret(),
            $user->getBackupCodes(),
            $keyIds,
        ];
    }
}
