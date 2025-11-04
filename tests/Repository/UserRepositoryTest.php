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
use Zenstruck\Foundry\Test\ResetDatabase;

class UserRepositoryTest extends AppKernelTestCase
{
    use ResetDatabase;

    private ?UserRepository $repository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = static::getContainer()->get(UserRepository::class);
    }

    public function testFindOneByEmail(): void
    {
        $user = UserFactory::createOne(['email' => 'test@example.com']);

        $found = $this->repository->findOneBy(['email' => 'test@example.com']);

        self::assertInstanceOf(User::class, $found);
        self::assertEquals($user->getEmail(), $found->getEmail());
    }

    public function testFindOneByUsername(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser']);

        $found = $this->repository->findOneBy(['username' => 'testuser']);

        self::assertInstanceOf(User::class, $found);
        self::assertEquals($user->getUsername(), $found->getUsername());
    }

    public function testFindEnabledUsers(): void
    {
        UserFactory::createMany(3, ['enabled' => true]);
        UserFactory::createMany(2, ['enabled' => false]);

        $results = $this->repository->findBy(['enabled' => true]);

        self::assertCount(3, $results);
        foreach ($results as $user) {
            self::assertTrue($user->isEnabled());
        }
    }

    public function testFindUsersByRole(): void
    {
        UserFactory::createMany(2, ['roles' => ['ROLE_USER', 'ROLE_ADMIN']]);
        UserFactory::createMany(3, ['roles' => ['ROLE_USER']]);

        $allUsers = $this->repository->findAll();
        $adminUsers = array_filter($allUsers, fn(User $user) => in_array('ROLE_ADMIN', $user->getRoles()));

        self::assertCount(2, $adminUsers);
    }
}
