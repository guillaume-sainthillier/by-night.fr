<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Tests\AppWebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The tests browse https://by-night.test, the only host SYMFONY_TRUSTED_HOSTS (.env.test) accepts: a URL the tests
 * read on another host would not be the site's.
 */
final class TrustedHostTest extends AppWebTestCase
{
    public function testTheSiteAnswersOnItsHost(): void
    {
        $client = self::createClient();

        $client->request('GET', '/a-propos');

        self::assertResponseIsSuccessful();
        self::assertSame('https://by-night.test/a-propos', $client->getRequest()->getUri());
    }

    public function testAnotherHostIsRefused(): void
    {
        $client = self::createClient();

        $client->request('GET', '/a-propos', server: ['HTTP_HOST' => 'localhost']);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
