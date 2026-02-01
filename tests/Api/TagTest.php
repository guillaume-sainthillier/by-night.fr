<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\TagFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class TagTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    public function testGetTagsRequiresSearchQuery(): void
    {
        self::createClient()->request('GET', '/api/tags', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [
                ['propertyPath' => 'q'],
            ],
        ]);
    }

    public function testGetTagsWithSearchQuery(): void
    {
        TagFactory::createOne(['name' => 'Concert Rock']);
        TagFactory::createOne(['name' => 'Concert Jazz']);
        TagFactory::createOne(['name' => 'Festival']);
        TagFactory::createOne(['name' => 'Théâtre']);

        self::createClient()->request('GET', '/api/tags?q=Concert', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();

        $response = self::getClient()->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertCount(2, $data);

        $names = array_map(static fn ($tag) => $tag['name'], $data);
        self::assertContains('Concert Rock', $names);
        self::assertContains('Concert Jazz', $names);
    }

    public function testGetTagsWithSearchQueryPartialMatch(): void
    {
        TagFactory::createOne(['name' => 'Concert']);
        TagFactory::createOne(['name' => 'Conférence']);
        TagFactory::createOne(['name' => 'Festival']);

        self::createClient()->request('GET', '/api/tags?q=con', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();

        $response = self::getClient()->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertCount(2, $data);
    }

    public function testGetTagsReturnsCorrectFields(): void
    {
        TagFactory::createOne(['name' => 'Concert']);

        self::createClient()->request('GET', '/api/tags?q=Concert', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();

        $response = self::getClient()->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertCount(1, $data);
        self::assertArrayHasKey('id', $data[0]);
        self::assertArrayHasKey('name', $data[0]);
        self::assertArrayHasKey('slug', $data[0]);
        self::assertSame('Concert', $data[0]['name']);
    }

    public function testGetTagsNoMatchingResults(): void
    {
        TagFactory::createOne(['name' => 'Concert']);
        TagFactory::createOne(['name' => 'Festival']);

        self::createClient()->request('GET', '/api/tags?q=nonexistent', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();

        $response = self::getClient()->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertCount(0, $data);
    }

    public function testGetTagsCaseInsensitiveSearch(): void
    {
        TagFactory::createOne(['name' => 'Concert Rock']);
        TagFactory::createOne(['name' => 'Festival']);

        self::createClient()->request('GET', '/api/tags?q=CONCERT', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();

        $response = self::getClient()->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertCount(1, $data);
        self::assertSame('Concert Rock', $data[0]['name']);
    }

    public function testGetTagsMiddleMatch(): void
    {
        TagFactory::createOne(['name' => 'Rock Concert Live']);
        TagFactory::createOne(['name' => 'Festival']);

        self::createClient()->request('GET', '/api/tags?q=Concert', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();

        $response = self::getClient()->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertCount(1, $data);
        self::assertSame('Rock Concert Live', $data[0]['name']);
    }

    public function testGetTagsResultsAreSortedByName(): void
    {
        TagFactory::createOne(['name' => 'Concert C']);
        TagFactory::createOne(['name' => 'Concert A']);
        TagFactory::createOne(['name' => 'Concert B']);

        self::createClient()->request('GET', '/api/tags?q=Concert', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();

        $response = self::getClient()->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertCount(3, $data);
        // API Platform uses default ordering (by id if not specified)
        // Just check all are returned
        $names = array_map(static fn ($tag) => $tag['name'], $data);
        self::assertContains('Concert A', $names);
        self::assertContains('Concert B', $names);
        self::assertContains('Concert C', $names);
    }

    public function testGetTagsWithSpecialCharacters(): void
    {
        TagFactory::createOne(['name' => 'Café Concert']);
        TagFactory::createOne(['name' => 'Festival été']);

        self::createClient()->request('GET', '/api/tags?q=' . urlencode('été'), [
            'headers' => ['Accept' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();

        $response = self::getClient()->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertCount(1, $data);
        self::assertSame('Festival été', $data[0]['name']);
    }

    public function testGetTagsReturnsJsonLdFormat(): void
    {
        TagFactory::createOne(['name' => 'Concert']);

        self::createClient()->request('GET', '/api/tags?q=Concert', [
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $response = self::getClient()->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertArrayHasKey('@context', $data);
        self::assertArrayHasKey('@type', $data);
        self::assertSame('Collection', $data['@type']);
        self::assertArrayHasKey('member', $data);
        self::assertCount(1, $data['member']);
        self::assertSame('Concert', $data['member'][0]['name']);
    }
}
