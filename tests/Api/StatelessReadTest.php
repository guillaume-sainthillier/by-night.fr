<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Api;

use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The autocompletes and the search are the same for every visitor: they never open a session, a member's included.
 */
final class StatelessReadTest extends AppWebTestCase
{
    #[DataProvider('readProvider')]
    public function testAMembersSessionIsLeftAlone(string $uri, int $statusCode): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        // Without a query: answered before the search engine, past the firewall and the session listener
        $client->request('GET', $uri, server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame($statusCode);
        self::assertSame([], $client->getResponse()->headers->getCookies());
    }

    public static function readProvider(): iterable
    {
        yield 'tags, which require a query' => ['/api/tags', 422];
        yield 'search, empty without one' => ['/api/search', 200];
    }
}
