<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Digest\WeekendDigestUnsubscribeUrl;
use App\Entity\User;
use App\Factory\CityFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;

/**
 * The link of a weekend selection, opened without a login.
 */
final class WeekendDigestControllerTest extends AppWebTestCase
{
    public function testOpeningTheLinkOnlyAsksForAConfirmation(): void
    {
        $client = self::createClient();
        $user = $this->subscriber();

        // Mail scanners open every link of an e-mail
        $client->request('GET', $this->url($user));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        self::assertSelectorTextContains('h1', 'Ne plus recevoir la sélection du week-end');
        self::assertSame(1, UserFactory::count(['id' => $user->getId(), 'weekendDigest' => true]));
    }

    public function testTheConfirmationUnsubscribes(): void
    {
        $client = self::createClient();
        $user = $this->subscriber();

        $client->request('GET', $this->url($user));
        $client->submitForm('Me désinscrire');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Vous êtes désinscrit');
        self::assertSame(1, UserFactory::count(['id' => $user->getId(), 'weekendDigest' => false]));
    }

    /**
     * RFC 8058: the mailbox's own "Se désabonner" button posts to the link of the List-Unsubscribe header.
     */
    public function testTheMailboxButtonUnsubscribesInOnePost(): void
    {
        $client = self::createClient();
        $user = $this->subscriber();

        $client->request('POST', $this->url($user), ['List-Unsubscribe' => 'One-Click']);

        self::assertResponseIsSuccessful();
        self::assertSame(1, UserFactory::count(['id' => $user->getId(), 'weekendDigest' => false]));
    }

    public function testALinkForAnotherMemberIsRefused(): void
    {
        $client = self::createClient();
        $user = $this->subscriber();
        $other = UserFactory::createOne(['city' => $user->getCity(), 'weekendDigest' => true]);

        $client->request('POST', str_replace('/' . $user->getId() . '?', '/' . $other->getId() . '?', $this->url($user)));

        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, UserFactory::count(['id' => $other->getId(), 'weekendDigest' => true]));
    }

    public function testALinkWithoutItsSignatureIsRefused(): void
    {
        $client = self::createClient();
        $user = $this->subscriber();

        $client->request('POST', '/newsletter/desinscription/' . $user->getId());

        self::assertResponseStatusCodeSame(404);
    }

    private function subscriber(): User
    {
        return UserFactory::createOne(['city' => CityFactory::toulouse(), 'weekendDigest' => true]);
    }

    private function url(User $user): string
    {
        return self::getContainer()->get(WeekendDigestUnsubscribeUrl::class)->generate($user);
    }
}
