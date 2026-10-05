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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Sets a member's password, hashed, never kept as typed. A new password logs out their other sessions and
 * remember-me cookies, which are signed with the former one. Does not flush.
 */
final readonly class PasswordManager
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * The password the member chose: on registration, from their profile, or after a reset.
     */
    public function change(User $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
    }

    /**
     * A password nobody knows: whoever knew the former one is locked out (UnverifiedAccountClaim).
     */
    public function scramble(User $user): void
    {
        $this->change($user, bin2hex(random_bytes(32)));
    }
}
