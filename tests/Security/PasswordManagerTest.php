<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\PasswordManager;
use App\Tests\AppKernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordManagerTest extends AppKernelTestCase
{
    public function testThePasswordIsKeptHashed(): void
    {
        $user = new User();

        self::getContainer()->get(PasswordManager::class)->change($user, 'swing-au-bikini');

        self::assertNotSame('swing-au-bikini', $user->getPassword());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'swing-au-bikini'));
    }

    public function testAScrambledPasswordLetsNoOneIn(): void
    {
        $user = new User();
        $passwords = self::getContainer()->get(PasswordManager::class);
        $passwords->change($user, 'swing-au-bikini');

        $passwords->scramble($user);

        self::assertFalse(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'swing-au-bikini'));
    }
}
