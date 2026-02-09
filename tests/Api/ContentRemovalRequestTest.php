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
use App\Entity\ContentRemovalRequest;
use App\Enum\ContentRemovalRequestStatus;
use App\Enum\ContentRemovalType;
use App\Factory\EventFactory;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ContentRemovalRequestTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    public function testRemovalRequestWithValidData(): void
    {
        $event = EventFactory::createOne(['name' => 'Test Event']);

        self::createClient()->request('POST', \sprintf('/api/events/%d/removal-request', $event->getId()), [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'email' => 'requester@example.com',
                'type' => ContentRemovalType::Image->value,
                'message' => 'Please remove this image as I own the copyright.',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');
        self::assertJsonContains([
            'success' => true,
            'message' => 'Votre demande a bien été envoyée. Nous la traiterons dans les plus brefs délais.',
        ]);
    }

    public function testRemovalRequestSendsEmail(): void
    {
        $event = EventFactory::createOne(['name' => 'Test Event for Email']);

        self::createClient()->request('POST', \sprintf('/api/events/%d/removal-request', $event->getId()), [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'email' => 'copyright-owner@example.com',
                'type' => ContentRemovalType::Event->value,
                'message' => 'This event contains my copyrighted content. Please remove it.',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertEmailHeaderSame($email, 'Subject', 'Demande de suppression de contenu - By Night');
        self::assertEmailHtmlBodyContains($email, 'Test Event for Email');
        self::assertEmailHtmlBodyContains($email, 'copyright-owner@example.com');
        self::assertEmailHtmlBodyContains($email, 'This event contains my copyrighted content');
        self::assertEmailHtmlBodyContains($email, 'Événement complet');
    }

    public function testRemovalRequestWithImageType(): void
    {
        $event = EventFactory::createOne();

        self::createClient()->request('POST', \sprintf('/api/events/%d/removal-request', $event->getId()), [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'email' => 'photographer@example.com',
                'type' => ContentRemovalType::Image->value,
                'message' => 'I am the photographer and I did not authorize use of this image.',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertEmailHtmlBodyContains($email, 'Image de couverture');
    }

    public function testRemovalRequestWithEmptyEmailFails(): void
    {
        $event = EventFactory::createOne();

        self::createClient()->request('POST', \sprintf('/api/events/%d/removal-request', $event->getId()), [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'email' => '',
                'type' => ContentRemovalType::Image->value,
                'message' => 'Please remove this content.',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'email',
                ],
            ],
        ]);
    }

    public function testRemovalRequestWithInvalidEmailFails(): void
    {
        $event = EventFactory::createOne();

        self::createClient()->request('POST', \sprintf('/api/events/%d/removal-request', $event->getId()), [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'email' => 'not-an-email',
                'type' => ContentRemovalType::Image->value,
                'message' => 'Please remove this content.',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'email',
                ],
            ],
        ]);
    }

    public function testRemovalRequestWithEmptyMessageFails(): void
    {
        $event = EventFactory::createOne();

        self::createClient()->request('POST', \sprintf('/api/events/%d/removal-request', $event->getId()), [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'email' => 'requester@example.com',
                'type' => ContentRemovalType::Image->value,
                'message' => '',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'message',
                ],
            ],
        ]);
    }

    public function testRemovalRequestWithTooShortMessageFails(): void
    {
        $event = EventFactory::createOne();

        self::createClient()->request('POST', \sprintf('/api/events/%d/removal-request', $event->getId()), [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'email' => 'requester@example.com',
                'type' => ContentRemovalType::Image->value,
                'message' => 'Short',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'message',
                ],
            ],
        ]);
    }

    public function testRemovalRequestWithMissingTypeFails(): void
    {
        $event = EventFactory::createOne();

        self::createClient()->request('POST', \sprintf('/api/events/%d/removal-request', $event->getId()), [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'email' => 'requester@example.com',
                'message' => 'Please remove this content from your website.',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'type',
                ],
            ],
        ]);
    }

    public function testRemovalRequestForNonExistentEventFails(): void
    {
        self::createClient()->request('POST', '/api/events/999999/removal-request', [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'email' => 'requester@example.com',
                'type' => ContentRemovalType::Image->value,
                'message' => 'Please remove this content.',
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testRemovalRequestWithEventUrls(): void
    {
        $event = EventFactory::createOne(['name' => 'Test Event with URLs']);

        self::createClient()->request('POST', \sprintf('/api/events/%d/removal-request', $event->getId()), [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'email' => 'requester@example.com',
                'type' => ContentRemovalType::Image->value,
                'message' => 'Please remove this image as I own the copyright.',
                'eventUrls' => [
                    'https://example.com/event/123',
                    'https://other-site.com/my-event',
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertEmailHtmlBodyContains($email, 'https://example.com/event/123');
        self::assertEmailHtmlBodyContains($email, 'https://other-site.com/my-event');
    }

    public function testRemovalRequestWithInvalidUrlFails(): void
    {
        $event = EventFactory::createOne();

        self::createClient()->request('POST', \sprintf('/api/events/%d/removal-request', $event->getId()), [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'email' => 'requester@example.com',
                'type' => ContentRemovalType::Image->value,
                'message' => 'Please remove this content.',
                'eventUrls' => ['not-a-valid-url'],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'eventUrls[0]',
                ],
            ],
        ]);
    }

    public function testRemovalRequestPersistsEntity(): void
    {
        $event = EventFactory::createOne(['name' => 'Test Event for Persistence']);

        self::createClient()->request('POST', \sprintf('/api/events/%d/removal-request', $event->getId()), [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'email' => 'persist-test@example.com',
                'type' => ContentRemovalType::Event->value,
                'message' => 'This is a test message for persistence.',
                'eventUrls' => ['https://example.com/event'],
            ],
        ]);

        self::assertResponseIsSuccessful();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = $entityManager->getRepository(ContentRemovalRequest::class);

        $requests = $repository->findBy(['email' => 'persist-test@example.com']);
        self::assertCount(1, $requests);

        $request = $requests[0];
        self::assertSame('persist-test@example.com', $request->getEmail());
        self::assertSame(ContentRemovalType::Event, $request->getType());
        self::assertSame('This is a test message for persistence.', $request->getMessage());
        self::assertSame(['https://example.com/event'], $request->getEventUrls());
        self::assertSame($event->getId(), $request->getEvent()->getId());
        self::assertSame(ContentRemovalRequestStatus::Pending, $request->getStatus());
        self::assertNull($request->getProcessedAt());
        self::assertNull($request->getProcessedBy());
    }
}
