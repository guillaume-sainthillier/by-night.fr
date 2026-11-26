<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Api;

use ApiPlatform\Test\Client;
use App\Enum\EventStatus;
use App\Factory\EventFactory;
use App\Factory\UserFactory;
use App\Tests\AppApiTestCase;
use Override;
use Symfony\Component\Security\Core\User\UserInterface;
use Zenstruck\Foundry\Persistence\Proxy;

use function Zenstruck\Foundry\Persistence\refresh;

final class EventActionTest extends AppApiTestCase
{
    #[Override]
    protected static ?bool $alwaysBootKernel = true;

    public function testCancelEventRequiresAuthentication(): void
    {
        $event = EventFactory::createOne();

        self::createClient()->request('PATCH', \sprintf('/api/events/%d', $event->getId()), [
            'json' => ['cancelled' => true],
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testCancelEventRequiresOwnership(): void
    {
        $owner = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $owner]);

        $this->createAuthenticatedClient($otherUser)->request('PATCH', \sprintf('/api/events/%d', $event->getId()), [
            'json' => ['cancelled' => true],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCancelEvent(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'status' => null]);
        $eventId = $event->getId();

        $this->createAuthenticatedClient($user)->request('PATCH', \sprintf('/api/events/%d', $eventId), [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['cancelled' => true],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');
        self::assertJsonContains(['cancelled' => true, 'draft' => false]);

        $updatedEvent = refresh($event);
        self::assertSame(EventStatus::Cancelled, $updatedEvent->getStatus());
    }

    public function testUncancelEvent(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'status' => EventStatus::Cancelled]);
        $eventId = $event->getId();

        $this->createAuthenticatedClient($user)->request('PATCH', \sprintf('/api/events/%d', $eventId), [
            'json' => ['cancelled' => false],
        ]);

        self::assertResponseIsSuccessful();

        $updatedEvent = refresh($event);
        self::assertNull($updatedEvent->getStatus());
    }

    public function testTakingACancellationBackLeavesAnotherStatusAlone(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'status' => EventStatus::Postponed]);

        $this->createAuthenticatedClient($user)->request('PATCH', \sprintf('/api/events/%d', $event->getId()), [
            'json' => ['cancelled' => false],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(EventStatus::Postponed, refresh($event)->getStatus());
    }

    public function testAPatchChangesOnlyWhatItNames(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'draft' => true, 'status' => EventStatus::Cancelled]);

        $this->createAuthenticatedClient($user)->request('PATCH', \sprintf('/api/events/%d', $event->getId()), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'body' => '{"draft": false}',
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['draft' => false, 'cancelled' => true]);
        $event = refresh($event);
        self::assertFalse($event->isDraft());
        self::assertSame(EventStatus::Cancelled, $event->getStatus());
    }

    public function testDraftEventRequiresAuthentication(): void
    {
        $event = EventFactory::createOne();

        self::createClient()->request('PATCH', \sprintf('/api/events/%d', $event->getId()), [
            'json' => ['draft' => true],
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testDraftEventRequiresOwnership(): void
    {
        $owner = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $owner]);

        $this->createAuthenticatedClient($otherUser)->request('PATCH', \sprintf('/api/events/%d', $event->getId()), [
            'json' => ['draft' => true],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testSetEventAsDraft(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'draft' => false]);
        $eventId = $event->getId();

        $this->createAuthenticatedClient($user)->request('PATCH', \sprintf('/api/events/%d', $eventId), [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['draft' => true],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');
        self::assertJsonContains(['draft' => true, 'cancelled' => false]);

        $updatedEvent = refresh($event);
        self::assertTrue($updatedEvent->isDraft());
    }

    public function testPublishEvent(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'draft' => true]);
        $eventId = $event->getId();

        $this->createAuthenticatedClient($user)->request('PATCH', \sprintf('/api/events/%d', $eventId), [
            'json' => ['draft' => false],
        ]);

        self::assertResponseIsSuccessful();

        $updatedEvent = refresh($event);
        self::assertFalse($updatedEvent->isDraft());
    }

    public function testParticipateRequiresAuthentication(): void
    {
        $event = EventFactory::createOne();

        self::createClient()->request('PUT', \sprintf('/api/events/%d/participation', $event->getId()), [
            'json' => ['going' => true],
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testParticipateToEvent(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['participations' => 0]);
        $eventId = $event->getId();

        $this->createAuthenticatedClient($user)->request('PUT', \sprintf('/api/events/%d/participation', $eventId), [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['going' => true],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');
        self::assertJsonContains(['going' => true, 'participations' => 1]);

        $updatedEvent = refresh($event);
        self::assertSame(1, $updatedEvent->getParticipations());
    }

    public function testUnparticipateFromEvent(): void
    {
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['participations' => 1]);
        $eventId = $event->getId();

        $client = $this->createAuthenticatedClient($user);

        // First participate
        $client->request('PUT', \sprintf('/api/events/%d/participation', $eventId), [
            'json' => ['going' => true],
        ]);

        // Then unparticipate
        $client->request('PUT', \sprintf('/api/events/%d/participation', $eventId), [
            'json' => ['going' => false],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['going' => false]);
    }

    public function testNoOneTakesPartInADraftTheyCannotSee(): void
    {
        $event = EventFactory::createOne(['draft' => true, 'participations' => 0]);

        $this->createAuthenticatedClient(UserFactory::createOne())->request('PUT', \sprintf('/api/events/%d/participation', $event->getId()), [
            'json' => ['going' => true],
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, refresh($event)->getParticipations());
    }

    public function testTheAuthorTakesPartInTheirDraft(): void
    {
        $event = EventFactory::createOne(['draft' => true, 'participations' => 0]);

        $this->createAuthenticatedClient($event->getUser())->request('PUT', \sprintf('/api/events/%d/participation', $event->getId()), [
            'json' => ['going' => true],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(1, refresh($event)->getParticipations());
    }

    public function testCancelNonExistentEvent(): void
    {
        $user = UserFactory::createOne();

        $this->createAuthenticatedClient($user)->request('PATCH', '/api/events/999999', [
            'json' => ['cancelled' => true],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    private function createAuthenticatedClient(UserInterface|Proxy $user): Client
    {
        $client = self::createClient();
        $client->loginUser($user);

        return $client;
    }
}
