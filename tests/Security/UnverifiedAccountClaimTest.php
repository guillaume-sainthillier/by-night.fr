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
use App\Factory\UserFactory;
use App\Security\UnverifiedAccountClaim;
use App\Social\Social;
use App\Social\SocialProvider;
use App\Tests\AppKernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UnverifiedAccountClaimTest extends AppKernelTestCase
{
    public function testTheOwnerOfTheAddressTakesAnUnverifiedAccountOver(): void
    {
        $user = $this->createUser(verified: false);
        $user->getOAuth()->setFacebookId('squatter-facebook-id');
        $passwordHash = $user->getPassword();

        self::assertTrue($this->getClaim()->claim($user, $this->getSocial(SocialProvider::GOOGLE), 'owner-google-id'));

        self::assertTrue($user->isVerified());
        self::assertNotSame($passwordHash, $user->getPassword());
        self::assertFalse($this->getHasher()->isPasswordValid($user, 'squatter-password'));
        self::assertNull($user->getOAuth()->getFacebookId(), 'The social accounts linked by whoever registered the address are unlinked');
    }

    public function testAVerifiedAccountIsLeftAlone(): void
    {
        $user = $this->createUser(verified: true);
        $passwordHash = $user->getPassword();

        self::assertFalse($this->getClaim()->claim($user, $this->getSocial(SocialProvider::GOOGLE), 'owner-google-id'));

        self::assertSame($passwordHash, $user->getPassword());
    }

    public function testAnAccountAlreadyLinkedToThisNetworkAccountIsLeftAlone(): void
    {
        $user = $this->createUser(verified: false);
        $user->getOAuth()->setGoogleId('owner-google-id');
        $passwordHash = $user->getPassword();

        self::assertFalse($this->getClaim()->claim($user, $this->getSocial(SocialProvider::GOOGLE), 'owner-google-id'));

        self::assertSame($passwordHash, $user->getPassword());
        self::assertFalse($user->isVerified());
    }

    private function createUser(bool $verified): User
    {
        $user = UserFactory::createOne(['verified' => $verified]);
        $user->setPassword($this->getHasher()->hashPassword($user, 'squatter-password'));

        return $user;
    }

    private function getClaim(): UnverifiedAccountClaim
    {
        return self::getContainer()->get(UnverifiedAccountClaim::class);
    }

    private function getSocial(string $name): Social
    {
        return self::getContainer()->get(SocialProvider::class)->getSocial($name);
    }

    private function getHasher(): UserPasswordHasherInterface
    {
        return self::getContainer()->get(UserPasswordHasherInterface::class);
    }
}
