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
use App\Factory\PublisherFactory;
use App\Tests\AppWebTestCase;
use App\Tests\Controller\Location\StubsAgendaSearch;
use DateTimeImmutable;
use stdClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use function Zenstruck\Foundry\force;

/**
 * The MCP server at /mcp, as an assistant calls it: the handshake, then the tools of the API Platform resources, which
 * answer what their GET answers (EventSearchTest).
 */
final class McpServerTest extends AppWebTestCase
{
    use StubsAgendaSearch;

    private const string PROTOCOL = '2025-06-18';

    /** An address of its own for each test: the server's throttle counts per address, across the tests */
    private string $clientIp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientIp = \sprintf('10.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(1, 254));
    }

    public function testTheServerOffersItsTools(): void
    {
        $client = self::createClient();
        $session = $this->initialize($client);

        $result = $this->call($client, $session, 'tools/list');

        $tools = array_column($result['tools'], null, 'name');
        ksort($tools);
        self::assertSame(['find_cities', 'get_event', 'search_events'], array_keys($tools));
        // The input of the GET, described by the same class
        self::assertSame(['city'], $tools['search_events']['inputSchema']['required']);
        self::assertSame(['anytime', 'today', 'tomorrow', 'this_weekend', 'this_week', 'this_month'], $tools['search_events']['inputSchema']['properties']['when']['enum']);
        self::assertSame('object', $tools['find_cities']['outputSchema']['type'], 'A tool answers an object');
        self::assertTrue($tools['get_event']['annotations']['readOnlyHint']);
    }

    public function testSearchEventsListsTheEventsOfACity(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $concert = EventFactory::new()->withDates(new DateTimeImmutable('+3 days'))->create([
            'name' => 'Concert de jazz',
            'place' => $bikini,
            'placeName' => 'Le Bikini',
            'placeCity' => 'Toulouse',
            'prices' => 'Gratuit',
            'publisher' => PublisherFactory::createOne(['name' => 'Le Collectif']),
        ]);
        // The search stub lives in the kernel, which the client would boot again for each request
        $client->disableReboot();
        $this->stubAgendaSearch(events: [$concert]);
        $session = $this->initialize($client);

        $result = $this->callTool($client, $session, 'search_events', ['city' => 'toulouse', 'when' => 'this_week', 'limit' => 5]);

        self::assertFalse($result['isError'] ?? false);
        $content = $result['structuredContent'];
        // The text is the same JSON, readable
        self::assertSame($content, json_decode($result['content'][0]['text'], true));
        self::assertStringNotContainsString('\\/', $result['content'][0]['text']);
        self::assertSame('Toulouse', $content['city']);
        self::assertSame(1, $content['total']);
        $event = $content['events'][0];
        self::assertSame($concert->getId(), $event['id']);
        self::assertSame('Concert de jazz', $event['name']);
        self::assertSame(\sprintf('https://by-night.test/toulouse/soiree/%s--%d', $concert->getSlug(), $concert->getId()), $event['url']);
        self::assertSame(['name' => 'Le Bikini', 'city' => 'Toulouse'], array_intersect_key($event['venue'], ['name' => 1, 'city' => 1]));
        self::assertTrue($event['free']);
        self::assertSame('Le Collectif', $event['organizer']);
        self::assertSame((new DateTimeImmutable('+3 days'))->format('Y-m-d'), $event['nextSession']['date'] ?? $concert->getStartDate()?->format('Y-m-d'));
    }

    public function testAnUnknownCityIsReportedToTheAssistant(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->stubAgendaSearch();
        $session = $this->initialize($client);

        $result = $this->callTool($client, $session, 'search_events', ['city' => 'nulle-part']);

        self::assertTrue($result['isError']);
        self::assertSame('No city "nulle-part": find_cities lists the cities a name matches.', $result['content'][0]['text']);
    }

    public function testFindCitiesListsTheCitiesOfAName(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create(['upcomingEvents' => force(42)]);
        $client->disableReboot();
        $this->stubAgendaSearch(cities: [$toulouse]);
        $session = $this->initialize($client);

        $result = $this->callTool($client, $session, 'find_cities', ['q' => 'toul']);

        self::assertFalse($result['isError'] ?? false);
        self::assertSame([['slug' => 'toulouse', 'name' => $toulouse->getFullName(), 'upcomingEvents' => 42]], $result['structuredContent']['cities']);
    }

    public function testAWrongArgumentIsRefusedBeforeTheTool(): void
    {
        $client = self::createClient();
        $session = $this->initialize($client);

        $this->post($client, $session, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => [
            'name' => 'search_events',
            'arguments' => ['city' => 'toulouse', 'from' => '26/11/2026'],
        ]]);

        $answer = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(-32602, $answer['error']['code']);
        self::assertStringContainsString('/from', $answer['error']['message']);
    }

    public function testAnUnknownEventIsReportedToTheAssistant(): void
    {
        $client = self::createClient();
        $session = $this->initialize($client);

        $result = $this->callTool($client, $session, 'get_event', ['id' => 999999]);

        self::assertTrue($result['isError']);
        self::assertSame('No event 999999.', $result['content'][0]['text']);
    }

    public function testGetEventGivesItsDetails(): void
    {
        $client = self::createClient();
        $event = EventFactory::new()->withDates(new DateTimeImmutable('+3 days'))->create(['name' => 'Concert de jazz', 'description' => '<p>Un <strong>trio</strong> de jazz.</p>']);
        $session = $this->initialize($client);

        $result = $this->callTool($client, $session, 'get_event', ['id' => $event->getId()]);

        self::assertFalse($result['isError'] ?? false);
        self::assertSame('Concert de jazz', $result['structuredContent']['event']['name']);
        self::assertSame('Un trio de jazz.', $result['structuredContent']['description']);
    }

    public function testGetEventLeadsAFamilysMemberToItsPage(): void
    {
        $client = self::createClient();
        $canonical = EventFactory::createOne(['name' => 'Concert de jazz']);
        $duplicate = EventFactory::createOne(['duplicateOf' => $canonical]);
        $session = $this->initialize($client);

        $result = $this->callTool($client, $session, 'get_event', ['id' => $duplicate->getId()]);

        self::assertSame($canonical->getId(), $result['structuredContent']['event']['id']);
    }

    public function testADraftIsNotFound(): void
    {
        $client = self::createClient();
        $draft = EventFactory::createOne(['draft' => true]);
        $session = $this->initialize($client);

        $result = $this->callTool($client, $session, 'get_event', ['id' => $draft->getId()]);

        self::assertTrue($result['isError']);
        self::assertSame(\sprintf('No event %d.', $draft->getId()), $result['content'][0]['text']);
    }

    public function testTheServerOpensNoSiteSession(): void
    {
        $client = self::createClient();
        $this->initialize($client);

        self::assertSame([], $client->getResponse()->headers->getCookies());
    }

    /**
     * The handshake: the id of the MCP session the next requests carry.
     */
    private function initialize(KernelBrowser $client): string
    {
        $this->post($client, null, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
            'protocolVersion' => self::PROTOCOL,
            'capabilities' => new stdClass(),
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]]);
        self::assertResponseIsSuccessful();
        $session = (string) $client->getResponse()->headers->get('Mcp-Session-Id');
        $this->post($client, $session, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);

        return $session;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function callTool(KernelBrowser $client, string $session, string $name, array $arguments): array
    {
        return $this->call($client, $session, 'tools/call', ['name' => $name, 'arguments' => $arguments]);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> the result of the request
     */
    private function call(KernelBrowser $client, string $session, string $method, array $params = []): array
    {
        $this->post($client, $session, ['jsonrpc' => '2.0', 'id' => 2, 'method' => $method, 'params' => [] === $params ? new stdClass() : $params]);
        self::assertResponseIsSuccessful();
        $answer = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('result', $answer, (string) $client->getResponse()->getContent());

        return $answer['result'];
    }

    /**
     * @param array<string, mixed> $message
     */
    private function post(KernelBrowser $client, ?string $session, array $message): void
    {
        $client->request('POST', '/mcp', server: array_filter([
            'REMOTE_ADDR' => $this->clientIp,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_SESSION_ID' => $session,
            'HTTP_MCP_PROTOCOL_VERSION' => null !== $session ? self::PROTOCOL : null,
        ]), content: json_encode($message, \JSON_THROW_ON_ERROR));
    }
}
