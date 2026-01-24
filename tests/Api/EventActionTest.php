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
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Factory\EventFactory;
use App\Factory\UserFactory;
use Symfony\Component\Security\Core\User\UserInterface;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class EventActionTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    public function testCancelEventRequiresAuthentication(): void
    {
        $event = EventFactory::createOne();

        static::createClient()->request('PUT', \sprintf('/api/events/%d/cancel', $event->getId()), [
            'json' => ['cancel' => true],
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testCancelEventRequiresOwnership(): void
    {
        $owner = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $owner]);

        self::createAuthenticatedClient($otherUser)->request('PUT', \sprintf('/api/events/%d/cancel', $event->getId()), [
            'json' => ['cancel' => true],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCancelEvent(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'status' => null]);
        $eventId = $event->getId();

        self::createAuthenticatedClient($user)->request('PUT', \sprintf('/api/events/%d/cancel', $eventId), [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['cancel' => true],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');
        self::assertJsonContains(['success' => true]);

        $updatedEvent = $event->_refresh();
        self::assertSame('ANNULÉ', $updatedEvent->getStatus());
    }

    public function testUncancelEvent(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'status' => 'ANNULÉ']);
        $eventId = $event->getId();

        self::createAuthenticatedClient($user)->request('PUT', \sprintf('/api/events/%d/cancel', $eventId), [
            'json' => ['cancel' => false],
        ]);

        self::assertResponseIsSuccessful();

        $updatedEvent = $event->_refresh();
        self::assertNull($updatedEvent->getStatus());
    }

    public function testDraftEventRequiresAuthentication(): void
    {
        $event = EventFactory::createOne();

        static::createClient()->request('PUT', \sprintf('/api/events/%d/draft', $event->getId()), [
            'json' => ['draft' => true],
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testDraftEventRequiresOwnership(): void
    {
        $owner = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $owner]);

        self::createAuthenticatedClient($otherUser)->request('PUT', \sprintf('/api/events/%d/draft', $event->getId()), [
            'json' => ['draft' => true],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testSetEventAsDraft(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'draft' => false]);
        $eventId = $event->getId();

        self::createAuthenticatedClient($user)->request('PUT', \sprintf('/api/events/%d/draft', $eventId), [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['draft' => true],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');
        self::assertJsonContains(['success' => true]);

        $updatedEvent = $event->_refresh();
        self::assertTrue($updatedEvent->isDraft());
    }

    public function testPublishEvent(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'draft' => true]);
        $eventId = $event->getId();

        self::createAuthenticatedClient($user)->request('PUT', \sprintf('/api/events/%d/draft', $eventId), [
            'json' => ['draft' => false],
        ]);

        self::assertResponseIsSuccessful();

        $updatedEvent = $event->_refresh();
        self::assertFalse($updatedEvent->isDraft());
    }

    public function testParticipateRequiresAuthentication(): void
    {
        $event = EventFactory::createOne();

        static::createClient()->request('PUT', \sprintf('/api/events/%d/participer', $event->getId()), [
            'json' => ['like' => true],
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testParticipateToEvent(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['participations' => 0]);
        $eventId = $event->getId();

        self::createAuthenticatedClient($user)->request('PUT', \sprintf('/api/events/%d/participer', $eventId), [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['like' => true],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');
        self::assertJsonContains(['success' => true, 'like' => true]);

        $updatedEvent = $event->_refresh();
        self::assertSame(1, $updatedEvent->getParticipations());
    }

    public function testUnparticipateFromEvent(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['participations' => 1]);
        $eventId = $event->getId();

        $client = self::createAuthenticatedClient($user);

        // First participate
        $client->request('PUT', \sprintf('/api/events/%d/participer', $eventId), [
            'json' => ['like' => true],
        ]);

        // Then unparticipate
        $client->request('PUT', \sprintf('/api/events/%d/participer', $eventId), [
            'json' => ['like' => false],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['success' => true, 'like' => false]);
    }

    public function testCancelNonExistentEvent(): void
    {
        $user = UserFactory::createOne();

        self::createAuthenticatedClient($user)->request('PUT', '/api/events/999999/cancel', [
            'json' => ['cancel' => true],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    private static function createAuthenticatedClient(UserInterface|Proxy $user): Client
    {
        $user = $user instanceof Proxy ? $user->_real() : $user;
        $client = static::createClient();
        $client->loginUser($user);

        return $client;
    }
}
