<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Publisher;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Newsletter\PublisherNewsSender;
use App\Tests\AppWebTestCase;

use function Zenstruck\Foundry\Persistence\refresh;

final class PublisherNewsControllerTest extends AppWebTestCase
{
    public function testTheLinkAsksBeforeUnsubscribing(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();

        // A mailbox's link checker opens the link: nothing changes until the button is pressed
        $client->request('GET', $this->unsubscribeUrl($user));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ne plus recevoir nos nouveautés');
        refresh($user);
        self::assertTrue($user->isPublisherNews());

        $client->submitForm('Me désinscrire');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'C\'est noté');
        refresh($user);
        self::assertFalse($user->isPublisherNews());
    }

    public function testTheMailboxUnsubscribesInOneClick(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();

        // RFC 8058: the mailbox posts to the List-Unsubscribe URL, without the member's session
        $client->request('POST', $this->unsubscribeUrl($user), ['List-Unsubscribe' => 'One-Click']);

        self::assertResponseIsSuccessful();
        refresh($user);
        self::assertFalse($user->isPublisherNews());
    }

    public function testAMemberAlreadyUnsubscribedIsToldSo(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['publisherNews' => false]);

        $client->request('GET', $this->unsubscribeUrl($user));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Vous êtes déjà désinscrit');
    }

    public function testALinkWhoseSignatureDoesNotMatchIsRefused(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $other = UserFactory::createOne();

        // The signature of a member's link does not unsubscribe another one
        $url = str_replace('/' . $user->getId() . '?', '/' . $other->getId() . '?', $this->unsubscribeUrl($user));
        $client->request('POST', $url);

        self::assertResponseStatusCodeSame(404);
        refresh($other);
        self::assertTrue($other->isPublisherNews());
    }

    private function unsubscribeUrl(User $user): string
    {
        return self::getContainer()->get(PublisherNewsSender::class)->getUnsubscribeUrl($user);
    }
}
