<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\AppOAuthFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class AppOAuthCrudControllerTest extends AppWebTestCase
{
    public function testIndexListsAppOAuths(): void
    {
        $client = $this->createAdminClient();
        AppOAuthFactory::createOne(['facebook_id' => 'fb123', 'google_id' => 'gl123']);
        AppOAuthFactory::createOne(['twitter_id' => 'tw123']);

        $client->request('GET', '/_administration/app-oauth');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'fb123');
        self::assertSelectorTextContains('table', 'gl123');
        self::assertSelectorTextContains('table', 'tw123');
    }

    public function testDetailShowsAppOAuth(): void
    {
        $client = $this->createAdminClient();
        $oauth = AppOAuthFactory::createOne(['facebook_id' => 'fb123']);

        $client->request('GET', \sprintf('/_administration/app-oauth/%d', $oauth->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'fb123');
    }

    public function testDeletingAnAppOAuthWorks(): void
    {
        $client = $this->createAdminClient();
        $oauth = AppOAuthFactory::createOne();
        $oauthId = $oauth->getId();

        $crawler = $client->request('GET', \sprintf('/_administration/app-oauth/%d', $oauthId));
        $token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $client->request('POST', \sprintf('/_administration/app-oauth/%d/delete', $oauthId), ['token' => $token]);

        self::assertResponseRedirects();
        self::assertSame(0, AppOAuthFactory::count(['id' => $oauthId]));
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        return $client;
    }
}
