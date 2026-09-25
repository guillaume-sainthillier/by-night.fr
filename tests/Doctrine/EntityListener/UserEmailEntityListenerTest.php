<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EntityListener;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Tests\AppKernelTestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

use function Zenstruck\Foundry\Persistence\save;

/**
 * An account registered with a password proves its address by e-mail, and proves it again
 * whenever the address changes. Accounts that come from a social network are left alone.
 */
#[RequiresPhpExtension('mjml')]
final class UserEmailEntityListenerTest extends AppKernelTestCase
{
    public function testARegistrationAsksToConfirmTheAddress(): void
    {
        $user = UserFactory::createOne(['fromLogin' => true, 'verified' => true]);

        self::assertEmailCount(1);
        self::assertSame(1, UserFactory::count(['id' => $user->getId(), 'verified' => false]));
    }

    public function testChangingTheAddressAsksToConfirmItAgain(): void
    {
        $user = $this->createVerifiedUser();

        $user->setEmail('new-address@example.com');
        save($user);

        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage(), 'To', 'new-address@example.com');
        self::assertSame(1, UserFactory::count(['id' => $user->getId(), 'verified' => false]), 'The new address is not verified yet');
    }

    public function testAnUpdateThatKeepsTheAddressLeavesTheAccountVerified(): void
    {
        $user = $this->createVerifiedUser();

        $user->setFirstname('Renamed');
        save($user);

        self::assertEmailCount(0);
        self::assertSame(1, UserFactory::count(['id' => $user->getId(), 'verified' => true]));
    }

    public function testAnAccountFromASocialNetworkIsNotAskedToConfirm(): void
    {
        $user = UserFactory::createOne(['fromLogin' => false, 'verified' => true]);

        $user->setEmail('new-address@example.com');
        save($user);

        self::assertEmailCount(0);
        self::assertSame(1, UserFactory::count(['id' => $user->getId(), 'verified' => true]));
    }

    private function createVerifiedUser(): User
    {
        // Registered, then confirmed: the registration mail is not what the test is about
        $user = UserFactory::createOne(['fromLogin' => true]);
        $user->setVerified(true);
        save($user);

        // A fresh kernel starts with an empty mailer log and an empty identity map
        self::bootKernel();

        return UserFactory::find(['id' => $user->getId()]);
    }
}
