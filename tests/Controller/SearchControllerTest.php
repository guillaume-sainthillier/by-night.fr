<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Elasticsearch refuses pages past its result window: they are answered without querying it.
 */
final class SearchControllerTest extends WebTestCase
{
    public function testASearchPagePastTheResultWindowIsNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', '/recherche/?q=concert&page=501');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAnApiSearchPagePastTheResultWindowIsEmpty(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/search?q=concert&page=5000', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertSame([], json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testAnApiAutocompletePagePastTheResultWindowIsEmpty(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/cities?q=toul&page=5000', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertSame([], json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }
}
