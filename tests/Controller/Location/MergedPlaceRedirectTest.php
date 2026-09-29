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
use App\Factory\PlaceFactory;
use App\Factory\PlaceLegacySlugFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The agenda page of a place merged into another one leads to the other one's.
 */
final class MergedPlaceRedirectTest extends WebTestCase
{
    public function testTheSlugOfAMergedPlaceRedirectsToThePlaceThatTookItsEvents(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        PlaceLegacySlugFactory::createOne(['place' => $bikini, 'slug' => 'le-bikini-1']);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini-1?type=concert');

        self::assertResponseRedirects('/toulouse/agenda/sortir-a/le-bikini?type=concert', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testALiveSlugOfTheCityWinsOverAMergedOne(): void
    {
        $this->requireRedis();
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        PlaceFactory::createOne(['name' => 'Le Bikini 1', 'slug' => 'le-bikini-1', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        PlaceLegacySlugFactory::createOne(['place' => $bikini, 'slug' => 'le-bikini-1']);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini-1');

        // The place named le-bikini-1 keeps its page
        self::assertResponseIsSuccessful();
    }

    public function testAMergedSlugOfAnotherCityIsNotFollowed(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $albi = CityFactory::createOne(['name' => 'Albi', 'country' => $toulouse->getCountry()]);
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'slug' => 'le-bikini', 'city' => $albi, 'country' => $albi->getCountry()]);
        PlaceLegacySlugFactory::createOne(['place' => $bikini, 'slug' => 'le-bikini-1']);

        $client->request('GET', '/toulouse/agenda/sortir-a/le-bikini-1');

        // No place of that slug: back to the city agenda
        self::assertResponseRedirects('/toulouse/agenda');
    }

    /**
     * The agenda page reads its cached counts from Redis, which CI does not run.
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
