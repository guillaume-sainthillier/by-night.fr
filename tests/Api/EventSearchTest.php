<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Api;

use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Tests\AppWebTestCase;
use App\Tests\Controller\Location\StubsAgendaSearch;
use DateTimeImmutable;

use function Zenstruck\Foundry\force;

/**
 * The read API the MCP tools share (McpServerTest): its GETs take the tools' input in their query string and answer
 * the same.
 */
final class EventSearchTest extends AppWebTestCase
{
    use StubsAgendaSearch;

    public function testTheEventsOfACity(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $concert = EventFactory::new()->withDates(new DateTimeImmutable('+3 days'))->create(['name' => 'Concert de jazz', 'place' => $bikini, 'placeName' => 'Le Bikini', 'placeCity' => 'Toulouse']);
        $client->disableReboot();
        $this->stubAgendaSearch(events: [$concert]);

        // The query string is cast to the types of the input ("5", "this_week")
        $client->request('GET', '/api/events?city=toulouse&when=this_week&limit=5', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'max-age=300, public, s-maxage=300');
        $answer = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Toulouse', $answer['city']);
        self::assertSame(1, $answer['total']);
        self::assertSame($concert->getId(), $answer['events'][0]['id']);
        // Tagged: an API client gives the link without a referrer
        self::assertSame(\sprintf('https://by-night.test/toulouse/soiree/%s--%d?utm_source=api&utm_medium=api&utm_campaign=search_events', $concert->getSlug(), $concert->getId()), $answer['events'][0]['url']);
        self::assertSame([], $client->getResponse()->headers->getCookies(), 'No session');
    }

    public function testAWrongInputIsRefused(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/events?city=toulouse&when=someday&limit=50', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(422);
        $paths = array_column(json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['violations'], 'propertyPath');
        sort($paths);
        self::assertSame(['limit', 'when'], $paths);
    }

    public function testADateWrittenOtherwiseIsRefused(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/events?city=toulouse&from=26/11/2026', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['from'], array_column(json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['violations'], 'propertyPath'));
    }

    public function testACityIsRequired(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/events', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['city'], array_column(json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['violations'], 'propertyPath'));
    }

    public function testAnUnknownCityIsNotFound(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->stubAgendaSearch();

        $client->request('GET', '/api/events?city=nulle-part', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnEventInDetail(): void
    {
        $client = self::createClient();
        $canonical = EventFactory::new()->withDates(new DateTimeImmutable('+3 days'))->create(['name' => 'Concert de jazz', 'description' => '<p>Un <strong>trio</strong> de jazz.</p>']);
        $duplicate = EventFactory::createOne(['duplicateOf' => $canonical]);

        $client->request('GET', \sprintf('/api/events/%d', $duplicate->getId()), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        $answer = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($canonical->getId(), $answer['event']['id'], "A family's member gives its page");
        self::assertSame('Un trio de jazz.', $answer['description']);
        self::assertSame((new DateTimeImmutable('+3 days'))->format('Y-m-d'), $answer['sessions'][0]['date']);
    }

    public function testADraftIsNotFound(): void
    {
        $client = self::createClient();
        $draft = EventFactory::createOne(['draft' => true]);

        $client->request('GET', \sprintf('/api/events/%d', $draft->getId()), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheCitiesOfAName(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create(['upcomingEvents' => force(42)]);
        $client->disableReboot();
        $this->stubAgendaSearch(cities: [$toulouse]);

        $client->request('GET', '/api/cities?q=toul', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertSame([['slug' => 'toulouse', 'name' => $toulouse->getFullName(), 'upcomingEvents' => 42]], json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * The query parameters of a GET are the properties of its input, as its MCP tool describes them.
     */
    public function testTheDocumentationListsTheInputAsQueryParameters(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/docs.jsonopenapi', server: ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);

        self::assertResponseIsSuccessful();
        $parameters = array_column(json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['paths']['/api/events']['get']['parameters'], null, 'name');
        self::assertSame(['city', 'when', 'from', 'to', 'category', 'query', 'price', 'limit'], array_keys($parameters));
        self::assertTrue($parameters['city']['required']);
        self::assertSame(['type' => 'string', 'format' => 'date'], $parameters['from']['schema']);
        self::assertSame(['concert', 'show', 'exhibition', 'family', 'student'], $parameters['category']['schema']['enum']);
        self::assertSame('How many events to list, 1 to 20.', $parameters['limit']['description']);
    }
}
