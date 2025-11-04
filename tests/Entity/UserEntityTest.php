<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Entity;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Tests\AppKernelTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserEntityTest extends AppKernelTestCase
{
    use ResetDatabase;

    public function testUserCreation(): void
    {
        $user = UserFactory::createOne([
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        self::assertInstanceOf(User::class, $user->object());
        self::assertEquals('testuser', $user->getUsername());
        self::assertEquals('test@example.com', $user->getEmail());
    }

    public function testUserIsEnabledByDefault(): void
    {
        $user = UserFactory::createOne();

        self::assertTrue($user->isEnabled());
    }

    public function testUserCanBeDisabled(): void
    {
        $user = UserFactory::createOne()->enabled(false);

        self::assertFalse($user->object()->isEnabled());
    }

    public function testUserHasDefaultRole(): void
    {
        $user = UserFactory::createOne();

        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testUserCanHaveAdminRole(): void
    {
        $user = UserFactory::createOne()->admin();

        $roles = $user->getRoles();
        self::assertContains('ROLE_USER', $roles);
        self::assertContains('ROLE_ADMIN', $roles);
    }

    public function testUserToString(): void
    {
        $user = UserFactory::createOne(['username' => 'johndoe']);

        self::assertEquals('johndoe', (string) $user->object());
    }

    public function testUserGetUserIdentifier(): void
    {
        $user = UserFactory::createOne(['email' => 'john@example.com']);

        self::assertEquals('john@example.com', $user->getUserIdentifier());
    }

    public function testUserFullName(): void
    {
        $user = UserFactory::createOne([
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);

        self::assertEquals('John', $user->getFirstname());
        self::assertEquals('Doe', $user->getLastname());
    }
}
