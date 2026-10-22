<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EventListener;

use App\Entity\Event;
use App\Factory\CommentFactory;
use App\Factory\EventFactory;
use App\Tests\AppKernelTestCase;
use Doctrine\DBAL\Connection;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\save;

final class CommentCountListenerTest extends AppKernelTestCase
{
    public function testCountsTheApprovedCommentsWithoutTheirReplies(): void
    {
        $event = EventFactory::createOne();
        $comment = CommentFactory::createOne(['event' => $event]);
        CommentFactory::createOne(['event' => $event, 'parent' => $comment]);
        CommentFactory::createOne(['event' => $event, 'approved' => false]);
        CommentFactory::createOne();

        self::assertSame(1, $event->getCommentCount());
        self::assertSame(1, $this->storedCount($event));
    }

    public function testAModeratedCommentIsNoLongerCounted(): void
    {
        $comment = CommentFactory::createOne();
        $event = $comment->getEvent();
        self::assertSame(1, $event->getCommentCount());

        $comment->setApproved(false);
        save($comment);

        self::assertSame(0, $event->getCommentCount());
        self::assertSame(0, $this->storedCount($event));
    }

    public function testACommentMovedToAnotherEventRecountsBoth(): void
    {
        $comment = CommentFactory::createOne();
        $from = $comment->getEvent();
        $to = EventFactory::createOne();

        $comment->setEvent($to);
        save($comment);

        self::assertSame([0, 1], [$this->storedCount($from), $this->storedCount($to)]);
    }

    public function testADeletedCommentIsNoLongerCounted(): void
    {
        $comment = CommentFactory::createOne();
        $event = $comment->getEvent();

        delete($comment);

        self::assertSame(0, $event->getCommentCount());
        self::assertSame(0, $this->storedCount($event));
    }

    private function storedCount(Event $event): int
    {
        $connection = self::getContainer()->get(Connection::class);

        return (int) $connection->fetchOne('SELECT comment_count FROM `event` WHERE id = ?', [$event->getId()]);
    }
}
