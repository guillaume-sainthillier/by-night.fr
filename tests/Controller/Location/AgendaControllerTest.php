<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Location;

use App\Factory\CityFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AgendaControllerTest extends WebTestCase
{
    public function testTheAgendaByPlaceWithoutAPlaceRedirectsToTheCityAgenda(): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();

        $client->request('GET', '/toulouse/agenda/sortir-a?slug=le-bikini');

        self::assertResponseRedirects('/toulouse/agenda', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testAListingWithoutResultsIsNotIndexable(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        CityFactory::toulouse()->create();

        // An invalid filter skips the Elasticsearch query and renders the listing with no result
        $client->request('GET', '/toulouse/agenda?range=not-a-number');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
    }

    /**
     * The listing page reads the event types through the Redis-backed cache, which CI does not run.
     */
    private function requireRedis(): void
    {
        $host = $_SERVER['REDIS_HOST'] ?? $_ENV['REDIS_HOST'] ?? 'localhost';
        $socket = @fsockopen((string) $host, 6379, $errno, $errstr, 1);
        if (false === $socket) {
            self::markTestSkipped(\sprintf('Redis is not reachable on %s:6379', $host));
        }

        fclose($socket);
    }
}
