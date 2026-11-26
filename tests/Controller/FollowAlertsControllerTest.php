<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\FollowFactory;
use App\Factory\UserFactory;
use App\Follow\FollowAlertUnsubscribeUrl;
use App\Tests\AppWebTestCase;

/**
 * The link of a follow alert, opened without a login.
 */
final class FollowAlertsControllerTest extends AppWebTestCase
{
    public function testOpeningTheLinkOnlyAsksForAConfirmation(): void
    {
        $client = self::createClient();
        $member = $this->follower();

        // Mail scanners open every link of an e-mail
        $client->request('GET', $this->url($member));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        self::assertSelectorTextContains('h1', 'Ne plus recevoir les nouveaux événements que vous suivez');
        self::assertSame(1, UserFactory::count(['id' => $member->getId(), 'followAlerts' => true]));
    }

    public function testTheConfirmationStopsTheAlertsAndKeepsTheFollows(): void
    {
        $client = self::createClient();
        $member = $this->follower();

        $client->request('GET', $this->url($member));
        $client->submitForm('Ne plus les recevoir');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', "C'est noté");
        self::assertSame(1, UserFactory::count(['id' => $member->getId(), 'followAlerts' => false]));
        self::assertSame(1, FollowFactory::count(['user' => $member]));
    }

    /**
     * RFC 8058: the mailbox's own "Se désabonner" button posts to the link of the List-Unsubscribe header.
     */
    public function testTheMailboxButtonStopsThemInOnePost(): void
    {
        $client = self::createClient();
        $member = $this->follower();

        $client->request('POST', $this->url($member), ['List-Unsubscribe' => 'One-Click']);

        self::assertResponseIsSuccessful();
        self::assertSame(1, UserFactory::count(['id' => $member->getId(), 'followAlerts' => false]));
    }

    public function testALinkForAnotherMemberIsRefused(): void
    {
        $client = self::createClient();
        $member = $this->follower();
        $other = $this->follower();

        $client->request('POST', str_replace('/' . $member->getId() . '?', '/' . $other->getId() . '?', $this->url($member)));

        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, UserFactory::count(['id' => $other->getId(), 'followAlerts' => true]));
    }

    private function follower(): User
    {
        $member = UserFactory::createOne();
        FollowFactory::createOne(['user' => $member]);

        return $member;
    }

    private function url(User $member): string
    {
        return self::getContainer()->get(FollowAlertUnsubscribeUrl::class)->generate($member);
    }
}
