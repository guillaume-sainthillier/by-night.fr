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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The pages managing the site's own social accounts live outside /_administration (their OAuth
 * callback URLs are registered with the networks): the controllers guard themselves.
 */
final class SocialAdminAccessTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAdminPages(): iterable
    {
        yield 'social accounts page' => ['GET', '/info/'];
        yield 'connection to the site google account' => ['GET', '/login-social/google_admin'];
        yield 'connection callback' => ['GET', '/login-social/check-google_admin'];
        yield 'disconnection' => ['POST', '/social/twitter_admin/deconnexion'];
    }

    #[DataProvider('provideAdminPages')]
    public function testAnonymousVisitorsAreSentToTheLoginPage(string $method, string $url): void
    {
        $client = self::createClient();

        $client->request($method, $url);

        self::assertResponseRedirects('/login');
    }

    #[DataProvider('provideAdminPages')]
    public function testMembersAreDenied(string $method, string $url): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request($method, $url);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminsReachTheSocialAccountsPage(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', '/info/');

        self::assertResponseRedirects();
        self::assertMatchesRegularExpression('~^/info/\d+$~', (string) $client->getResponse()->headers->get('Location'));
    }
}
