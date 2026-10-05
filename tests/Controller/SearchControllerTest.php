<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Factory\CityFactory;
use App\Tests\AppWebTestCase;
use App\Tests\Controller\Location\StubsAgendaSearch;
use Symfony\Component\HttpFoundation\Response;

/**
 * Elasticsearch refuses pages past its result window: they are answered without querying it.
 */
final class SearchControllerTest extends AppWebTestCase
{
    use StubsAgendaSearch;

    /**
     * The header sends the city of its page (data-search-page-url): the search page keeps it for the next search.
     */
    public function testTheSearchPageKeepsTheCityItCameFrom(): void
    {
        $client = self::createClient();
        CityFactory::createOne(['slug' => 'toulouse']);
        $this->stubAgendaSearch();

        $client->request('GET', '/recherche/?q=jazz&type=evenements&city=toulouse');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[type="hidden"][name="city"][value="toulouse"]');
    }

    /**
     * A slug sent before its city was renamed or merged names no city: the search goes on without one.
     */
    public function testAnUnknownCityIsLeftOut(): void
    {
        $client = self::createClient();
        $this->stubAgendaSearch();

        $client->request('GET', '/recherche/?q=jazz&type=evenements&city=atlantide');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form input[name="city"]');
    }

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
