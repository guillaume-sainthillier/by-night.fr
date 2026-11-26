<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Api;

use App\Factory\FollowFactory;
use App\Factory\PlaceFactory;
use App\Factory\PublisherFactory;
use App\Factory\UserFactory;
use App\Tests\AppApiTestCase;
use Override;

final class FollowTest extends AppApiTestCase
{
    #[Override]
    protected static ?bool $alwaysBootKernel = true;

    public function testFollowingNeedsAnAccount(): void
    {
        $publisher = PublisherFactory::createOne();

        self::createClient()->request('PUT', \sprintf('/api/publishers/%d/follow', $publisher->getId()), [
            'json' => ['following' => true],
        ]);

        self::assertResponseRedirects('/login');
        self::assertSame(0, FollowFactory::count());
    }

    public function testAMemberFollowsAPublisherThenStops(): void
    {
        $member = UserFactory::createOne();
        $publisher = PublisherFactory::createOne();
        FollowFactory::createOne(['publisher' => $publisher]);
        $client = self::createClient();
        $client->loginUser($member);

        $client->request('PUT', \sprintf('/api/publishers/%d/follow', $publisher->getId()), [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['following' => true],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['following' => true, 'followers' => 2]);
        self::assertSame(1, FollowFactory::count(['user' => $member, 'publisher' => $publisher]));

        // Twice is still once
        $client->request('PUT', \sprintf('/api/publishers/%d/follow', $publisher->getId()), ['json' => ['following' => true]]);
        self::assertJsonContains(['following' => true, 'followers' => 2]);

        $client->request('PUT', \sprintf('/api/publishers/%d/follow', $publisher->getId()), ['json' => ['following' => false]]);
        self::assertJsonContains(['following' => false, 'followers' => 1]);
        self::assertSame(0, FollowFactory::count(['user' => $member]));
    }

    public function testAMemberFollowsAVenue(): void
    {
        $member = UserFactory::createOne();
        $place = PlaceFactory::createOne();
        $client = self::createClient();
        $client->loginUser($member);

        $client->request('PUT', \sprintf('/api/places/%d/follow', $place->getId()), ['json' => ['following' => true]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['following' => true, 'followers' => 1]);
        self::assertSame(1, FollowFactory::count(['user' => $member, 'place' => $place]));
    }

    public function testAnUnknownPublisherIsNotFound(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('PUT', '/api/publishers/999999/follow', ['json' => ['following' => true]]);

        self::assertResponseStatusCodeSame(404);
    }
}
