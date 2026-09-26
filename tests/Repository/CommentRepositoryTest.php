<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Repository;

use App\Factory\CommentFactory;
use App\Factory\UserFactory;
use App\Repository\CommentRepository;
use App\Tests\AppKernelTestCase;

final class CommentRepositoryTest extends AppKernelTestCase
{
    public function testAMembersCommentsCountTheAnswersButNotTheRejectedOnes(): void
    {
        $member = UserFactory::createOne();
        $comment = CommentFactory::createOne(['user' => $member]);
        CommentFactory::createOne(['user' => $member, 'parent' => $comment, 'event' => $comment->getEvent()]);
        CommentFactory::createOne(['user' => $member, 'approved' => false]);
        CommentFactory::createOne();

        self::assertSame(2, self::getContainer()->get(CommentRepository::class)->countApprovedByUser($member));
    }
}
