<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Repository;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Repository\UserRepository;
use App\Tests\AppKernelTestCase;

final class UserRepositoryTest extends AppKernelTestCase
{
    public function testAMemberLogsInWithTheirEmailOrTheirUsername(): void
    {
        $member = UserFactory::createOne(['username' => 'jeanne', 'email' => 'jeanne@example.org']);

        self::assertSame($member->getId(), $this->loadId('jeanne@example.org'));
        self::assertSame($member->getId(), $this->loadId('jeanne'));
        self::assertNull($this->loadId('nobody'));
    }

    public function testAUsernameEqualToAnotherMembersEmailDoesNotLockThemOut(): void
    {
        $victim = UserFactory::createOne(['username' => 'jeanne', 'email' => 'jeanne@example.org']);
        UserFactory::createOne(['username' => 'jeanne@example.org', 'email' => 'squatter@example.org']);

        self::assertSame($victim->getId(), $this->loadId('jeanne@example.org'));
    }

    private function loadId(string $identifier): ?int
    {
        $user = self::getContainer()->get(UserRepository::class)->loadUserByIdentifier($identifier);

        return $user instanceof User ? $user->getId() : null;
    }
}
