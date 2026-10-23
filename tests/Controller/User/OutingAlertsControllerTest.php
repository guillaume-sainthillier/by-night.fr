<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\User;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use Symfony\Component\HttpFoundation\UriSigner;

/**
 * The link of an outing change alert that turns the alerts off, logged in or not.
 */
final class OutingAlertsControllerTest extends AppWebTestCase
{
    public function testTheLinkOfTheAlertAsksBeforeTurningThemOff(): void
    {
        $client = self::createClient();
        $member = UserFactory::createOne();

        $crawler = $client->request('GET', $this->signedUrl($member));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        self::assertTrue(UserFactory::find(['id' => $member->getId()])->isOutingAlerts(), 'a link checker opening it turns nothing off');

        $client->submit($crawler->selectButton('Ne plus être prévenu')->form());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty-title', 'Vous ne serez plus prévenu');
        self::assertFalse(UserFactory::find(['id' => $member->getId()])->isOutingAlerts());
    }

    public function testAMailAppTurnsThemOffInOneClick(): void
    {
        $client = self::createClient();
        $member = UserFactory::createOne();

        $client->request('POST', $this->signedUrl($member), ['List-Unsubscribe' => 'One-Click']);

        self::assertResponseIsSuccessful();
        self::assertSame('', $client->getResponse()->getContent());
        self::assertFalse(UserFactory::find(['id' => $member->getId()])->isOutingAlerts());
    }

    public function testAnUnsignedLinkTurnsNothingOff(): void
    {
        $client = self::createClient();
        $member = UserFactory::createOne();
        $other = UserFactory::createOne();
        // The signature of another member's link
        $forged = str_replace('/' . $other->getId() . '/', '/' . $member->getId() . '/', $this->signedUrl($other));

        $client->request('POST', '/alertes-sorties/' . $member->getId() . '/desabonnement', ['List-Unsubscribe' => 'One-Click']);
        self::assertResponseStatusCodeSame(404);
        $client->request('POST', $forged, ['List-Unsubscribe' => 'One-Click']);
        self::assertResponseStatusCodeSame(404);

        self::assertTrue(UserFactory::find(['id' => $member->getId()])->isOutingAlerts());
    }

    private function signedUrl(User $member): string
    {
        /** @var UriSigner $signer */
        $signer = self::getContainer()->get(UriSigner::class);

        return $signer->sign('https://by-night.test/alertes-sorties/' . $member->getId() . '/desabonnement');
    }
}
