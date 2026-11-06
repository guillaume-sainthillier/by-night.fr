<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\CommentFactory;
use App\Factory\EventFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class CommentCrudControllerTest extends AppWebTestCase
{
    public function testIndexListsComments(): void
    {
        $client = $this->createAdminClient();
        $event = EventFactory::createOne();
        $user = UserFactory::createOne(['username' => 'commentateur']);
        CommentFactory::createOne(['comment' => 'Un super événement !', 'event' => $event, 'user' => $user]);

        $client->request('GET', '/_administration/comment');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Un super événement !');
        self::assertSelectorTextContains('table', 'commentateur');
    }

    public function testDetailShowsComment(): void
    {
        $client = $this->createAdminClient();
        $comment = CommentFactory::createOne(['comment' => 'À voir absolument']);

        $client->request('GET', \sprintf('/_administration/comment/%d', $comment->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'À voir absolument');
    }

    public function testEditingACommentWorks(): void
    {
        $client = $this->createAdminClient();
        $comment = CommentFactory::createOne(['comment' => 'Ancien commentaire', 'approved' => false]);

        $crawler = $client->request('GET', \sprintf('/_administration/comment/%d/edit', $comment->getId()));
        $form = $crawler->selectButton('Sauvegarder les modifications')->form();
        $client->submit($form, [
            'Comment[comment]' => 'Commentaire mis à jour',
            'Comment[approved]' => true,
        ]);

        self::assertResponseRedirects();
        $comment = CommentFactory::find(['id' => $comment->getId()]);
        self::assertSame('Commentaire mis à jour', $comment->getComment());
        self::assertTrue($comment->isApproved());
    }

    public function testDeletingACommentWorks(): void
    {
        $client = $this->createAdminClient();
        $comment = CommentFactory::createOne();
        $commentId = $comment->getId();

        $crawler = $client->request('GET', \sprintf('/_administration/comment/%d', $commentId));
        $token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $client->request('POST', \sprintf('/_administration/comment/%d/delete', $commentId), ['token' => $token]);

        self::assertResponseRedirects();
        self::assertSame(0, CommentFactory::count(['id' => $commentId]));
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        return $client;
    }
}
