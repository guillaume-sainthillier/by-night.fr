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
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Elastica\NodePool\RoundRobinResurrect;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;

/**
 * Elasticsearch restarting (BY-NIGHTFR-69E, 6AH, 6B4): the pages that search answer 503 with a Retry-After, logged as
 * a warning, which Sentry does not receive.
 */
final class ElasticsearchOutageTest extends AppWebTestCase
{
    private const array ELASTICSEARCH_HEADERS = ['content-type' => 'application/json', 'x-elastic-product' => 'Elasticsearch'];

    public function testTheAgendaOfALocationIsUnavailableWhileTheNodeIsDown(): void
    {
        $client = $this->createClientWithElasticsearch(info: ['error' => 'Connection refused']);
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse');

        $this->assertUnavailable($client);
    }

    public function testTheAgendaOfALocationIsUnavailableUntilTheShardsAreBack(): void
    {
        $client = $this->createClientWithElasticsearch(
            '{"error":{"root_cause":[{"type":"no_shard_available_action_exception","reason":null}],"type":"search_phase_execution_exception","reason":"all shards failed"},"status":503}',
            ['http_code' => Response::HTTP_SERVICE_UNAVAILABLE, 'response_headers' => self::ELASTICSEARCH_HEADERS],
        );
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse');

        $this->assertUnavailable($client);
    }

    /**
     * The search page reads its lazy paginators in the template, which wraps the failure in a Twig\Error\RuntimeError.
     */
    public function testTheSearchPageIsUnavailableWhileTheNodeIsDown(): void
    {
        $client = $this->createClientWithElasticsearch(info: ['error' => 'Connection refused']);

        $client->request('GET', '/recherche/?q=concert');

        $this->assertUnavailable($client);
    }

    public function testTheSearchApiIsUnavailableWhileTheNodeIsDown(): void
    {
        $client = $this->createClientWithElasticsearch(info: ['error' => 'Connection refused']);

        $client->request('GET', '/api/search?q=concert', server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertUnavailable($client);
    }

    /**
     * Any other failure of Elasticsearch is a bug, still a 500 logged as an error.
     */
    public function testAnotherServerErrorOfElasticsearchStaysAnError(): void
    {
        $client = $this->createClientWithElasticsearch(
            '{"error":{"type":"illegal_state_exception","reason":"boom"},"status":500}',
            ['http_code' => Response::HTTP_INTERNAL_SERVER_ERROR, 'response_headers' => self::ELASTICSEARCH_HEADERS],
        );

        $client->request('GET', '/recherche/?q=concert');

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertGreaterThan(0, $this->loggerCollector($client)->countErrors());
    }

    /**
     * A real Elastica client, built as in production (its node pool, no logger), whose HTTP requests all get the
     * response given. Only for one request: the client boots a new kernel for the next one.
     *
     * @param array<string, mixed> $info the MockResponse options
     */
    private function createClientWithElasticsearch(string $body = '', array $info = []): KernelBrowser
    {
        $client = self::createClient();
        $client->enableProfiler();

        $http = new MockHttpClient(static fn (): MockResponse => new MockResponse($body, $info));
        $elastica = new Client([
            'hosts' => ['http://elasticsearch.test:9200'],
            'transport_config' => ['http_client' => new Psr18Client($http), 'node_pool' => RoundRobinResurrect::create()],
        ]);
        self::getContainer()->set('fos_elastica.client.default', $elastica);

        return $client;
    }

    private function assertUnavailable(KernelBrowser $client): void
    {
        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        self::assertResponseHeaderSame('Retry-After', '120');
        self::assertSame(0, $this->loggerCollector($client)->countErrors());
    }

    private function loggerCollector(KernelBrowser $client): LoggerDataCollector
    {
        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        $collector = $profile->getCollector('logger');
        self::assertInstanceOf(LoggerDataCollector::class, $collector);

        return $collector;
    }
}
