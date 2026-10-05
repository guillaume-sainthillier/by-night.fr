<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Manager;

use App\Entity\User;
use App\Factory\CommentFactory;
use App\Factory\EventFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use App\Manager\UserRemover;
use App\Tests\AppKernelTestCase;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\ORM\EntityManagerInterface;

final class UserRemoverTest extends AppKernelTestCase
{
    /**
     * Their own event goes too, with the favourites and the replies of others; the events they only followed are
     * recounted from the calendars left.
     */
    public function testAMemberGoesWithTheirEventsAndTheirTraces(): void
    {
        $user = UserFactory::createOne();
        $userId = $user->getId();
        $own = EventFactory::createOne(['user' => $user]);
        $ownId = $own->getId();
        UserEventFactory::createOne(['user' => $user, 'event' => $own]);
        UserEventFactory::createOne(['event' => $own]);
        $followed = EventFactory::createOne(['participations' => 9]);
        UserEventFactory::createOne(['user' => $user, 'event' => $followed]);
        UserEventFactory::createOne(['event' => $followed]);
        $comment = CommentFactory::createOne(['user' => $user, 'event' => $followed]);
        CommentFactory::createOne(['event' => $followed, 'parent' => $comment]);

        self::getContainer()->get(UserRemover::class)->remove($user, true);

        self::assertSame(0, UserFactory::count(['id' => $userId]));
        self::assertSame(0, EventFactory::count(['id' => $ownId]));
        self::assertSame(0, CommentFactory::count());
        self::assertSame(1, UserEventFactory::count());
        self::assertSame(1, EventFactory::find(['id' => $followed->getId()])->getParticipations());
    }

    /**
     * The favourites are loaded with their events: deleting a member reads as much for 1 favourite as for 5.
     */
    public function testTheReadsDoNotGrowWithTheFavourites(): void
    {
        // Every fixture first: a fresh kernel reseeds Faker, whose tag names would come again
        $few = $this->createMemberFollowing(1);
        $many = $this->createMemberFollowing(5);

        self::assertSame($this->countReadsToRemove($few), $this->countReadsToRemove($many));
    }

    private function createMemberFollowing(int $events): int
    {
        $user = UserFactory::createOne();
        UserEventFactory::createMany($events, ['user' => $user]);

        return (int) $user->getId();
    }

    private function countReadsToRemove(int $userId): int
    {
        // A fresh kernel: nothing loaded yet but the member, as when they delete their account
        self::bootKernel();
        $user = self::getContainer()->get(EntityManagerInterface::class)->find(User::class, $userId);
        self::assertNotNull($user);
        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        self::assertInstanceOf(BacktraceDebugDataHolder::class, $queries);
        $queries->reset();

        self::getContainer()->get(UserRemover::class)->remove($user, false);

        return \count(array_filter(
            $queries->getData()['default'] ?? [],
            static fn (array $query): bool => str_starts_with(ltrim((string) $query['sql']), 'SELECT'),
        ));
    }
}
