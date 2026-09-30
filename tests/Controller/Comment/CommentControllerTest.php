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
use App\Factory\EventFactory;
use App\Factory\UserFactory;
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

    public function testTheCommentsOfADraftAreOnlyForThoseWhoCanSeeIt(): void
    {
        $client = self::createClient();
        $author = UserFactory::createOne();
        $draft = EventFactory::createOne(['user' => $author, 'draft' => true]);
        $comment = CommentFactory::createOne(['comment' => 'Pensez à réserver', 'event' => $draft, 'user' => $author]);
        CommentFactory::createOne(['comment' => 'Merci pour le rappel', 'event' => $draft, 'parent' => $comment]);
        $paths = [
            \sprintf('/commentaire/%d/1', $draft->getId()),
            \sprintf('/commentaire/%d/reponses/1', $comment->getId()),
        ];

        foreach ($paths as $path) {
            $client->request('GET', $path);

            self::assertResponseStatusCodeSame(404, $path);
        }

        // Nor can another member write on it
        $client->loginUser(UserFactory::createOne());
        foreach ([...$paths, \sprintf('/commentaire/%d/nouveau', $draft->getId()), \sprintf('/commentaire/%d/repondre', $comment->getId())] as $path) {
            $client->request('GET', $path);

            self::assertResponseStatusCodeSame(404, $path);
        }

        $client->loginUser($author);
        $client->request('GET', $paths[0]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Pensez à réserver');
    }
}
