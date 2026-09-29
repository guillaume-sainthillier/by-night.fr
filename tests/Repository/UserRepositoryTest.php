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
use App\Factory\EventFactory;
use App\Factory\UserEventFactory;
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

    public function testTheParticipantsOfAnEventAreTheMembersGoingTheLastToSaySoFirst(): void
    {
        $event = EventFactory::createOne();
        $first = UserFactory::createOne();
        $second = UserFactory::createOne();
        $third = UserFactory::createOne();
        UserEventFactory::createOne(['event' => $event, 'user' => $first]);
        UserEventFactory::createOne(['event' => $event, 'user' => $second]);
        UserEventFactory::createOne(['event' => $event, 'user' => $third]);
        // Only interested, and going to another event
        UserEventFactory::createOne(['event' => $event, 'going' => false, 'wish' => true]);
        UserEventFactory::createOne(['event' => EventFactory::createOne()]);

        $participants = self::getContainer()->get(UserRepository::class)->findEventParticipants($event, 2);

        self::assertSame(
            [$third->getId(), $second->getId()],
            array_map(static fn (User $user): ?int => $user->getId(), $participants),
        );
    }

    private function loadId(string $identifier): ?int
    {
        $user = self::getContainer()->get(UserRepository::class)->loadUserByIdentifier($identifier);

        return $user instanceof User ? $user->getId() : null;
    }
}
