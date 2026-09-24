<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security;

use App\Entity\User;
use App\Social\Social;
use App\Social\SocialProvider;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * A social login matched an account through its e-mail. Registering needs no verification, so when
 * that account was never verified, whoever created it may have typed an address that is not theirs:
 * the owner of the address, vouched for by the network, takes the account over and the one who
 * registered it loses every way back in. A new random password invalidates their sessions and
 * remember-me cookies, and the social accounts they could have linked are unlinked.
 */
final readonly class UnverifiedAccountClaim
{
    private const array USER_SOCIALS = [SocialProvider::FACEBOOK, SocialProvider::GOOGLE, SocialProvider::TWITTER];

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private SocialProvider $socialProvider,
    ) {
    }

    /**
     * @return bool whether the account was taken over
     */
    public function claim(User $user, Social $social, string $socialId): bool
    {
        // A verified e-mail proves the account is its owner's, and an account already linked to
        // this network account was matched by its id: nothing to take back in both cases
        if (true === $user->isVerified() || $socialId === $social->getUserSocialId($user)) {
            return false;
        }

        $user
            ->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))))
            ->setVerified(true);

        foreach (self::USER_SOCIALS as $name) {
            $otherSocial = $this->socialProvider->getSocial($name);
            if ($otherSocial !== $social && null !== $otherSocial->getUserSocialId($user)) {
                $otherSocial->disconnectUser($user);
            }
        }

        return true;
    }
}
