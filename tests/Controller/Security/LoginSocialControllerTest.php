<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Security;

use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use App\Tests\Security\LogsInByRememberMe;

/**
 * The social login, and the connection of a network from the account, both start on /login-social/{service}
 */
final class LoginSocialControllerTest extends AppWebTestCase
{
    use LogsInByRememberMe;

    public function testAVisitorIsSentToTheNetwork(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login-social/google');

        self::assertResponseRedirects();
        self::assertStringStartsWith('https://accounts.google.com/', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testAMemberLoggedInConnectsANetwork(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/login-social/google');

        self::assertStringStartsWith('https://accounts.google.com/', (string) $client->getResponse()->headers->get('Location'));
    }

    /**
     * Only a member logged in this session connects a network to their account (UserSocialAuthenticator): one a
     * remember-me cookie logged in goes through the login first, then back here
     */
    public function testARememberedMemberLogsInAgainBeforeConnectingANetwork(): void
    {
        $client = self::createClient();
        $member = self::createRememberedMember();
        self::logInByRememberMe($client, $member);

        $client->request('GET', '/login-social/google');
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        $client->submitForm('Se connecter', ['username' => (string) $member->getEmail(), 'password' => self::REMEMBERED_PASSWORD]);

        self::assertResponseRedirects('https://by-night.test/login-social/google');
    }
}
