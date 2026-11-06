<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\UserFactory;
use App\Factory\UserOAuthFactory;
use App\Tests\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class UserOAuthCrudControllerTest extends AppWebTestCase
{
    public function testIndexListsUserOAuths(): void
    {
        $client = $this->createAdminClient();
        UserOAuthFactory::createOne(['facebook_id' => 'fb_user']);
        UserOAuthFactory::createOne(['google_id' => 'gl_user']);

        $client->request('GET', '/_administration/user-oauth');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'fb_user');
        self::assertSelectorTextContains('table', 'gl_user');
    }

    public function testDetailShowsUserOAuth(): void
    {
        $client = $this->createAdminClient();
        $oauth = UserOAuthFactory::createOne(['facebook_id' => 'fb_detail']);

        $client->request('GET', \sprintf('/_administration/user-oauth/%d', $oauth->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'fb_detail');
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        return $client;
    }
}
