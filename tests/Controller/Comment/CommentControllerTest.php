<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Comment;

use App\Factory\CommentFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CommentControllerTest extends WebTestCase
{
    public function testAnUnapprovedReplyIsNotListed(): void
    {
        $client = self::createClient();
        $comment = CommentFactory::createOne(['comment' => 'Qui vient ce soir ?']);
        CommentFactory::createOne(['comment' => 'Moi, avec plaisir', 'event' => $comment->getEvent(), 'parent' => $comment]);
        CommentFactory::createOne(['comment' => 'Réponse retirée par la modération', 'event' => $comment->getEvent(), 'parent' => $comment, 'approved' => false]);

        $client->request('GET', \sprintf('/commentaire/%d/1', $comment->getEvent()->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Moi, avec plaisir');
        self::assertSelectorTextNotContains('body', 'Réponse retirée par la modération');
    }
}
