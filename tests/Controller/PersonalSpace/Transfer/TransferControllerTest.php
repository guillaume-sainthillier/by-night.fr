<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\PersonalSpace\Transfer;

use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;

final class TransferControllerTest extends AppWebTestCase
{
    public function testThePageSubscribesTheMemberToTheirChannel(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/espace-perso/import-export');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/espace-perso/imports"] input[type="file"]');
        self::assertSelectorExists('form[action="/espace-perso/exports"]');
        $cookie = $client->getResponse()->headers->getCookies()[0] ?? null;
        self::assertSame('__Secure-mercure_access_token', $cookie?->getName());
        self::assertSame('/.well-known/mercure', $cookie->getPath());
        self::assertStringContainsString(
            rawurlencode('https://by-night.test/users/' . $user->getId()),
            (string) $crawler->filter('[data-page]')->attr('data-page-params'),
        );
    }

    public function testAVisitorIsAskedToLogIn(): void
    {
        $client = self::createClient();
        $client->request('GET', '/espace-perso/import-export');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }
}
