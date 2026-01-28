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
use App\Factory\UserFactory;
use Symfony\Component\Security\Core\User\UserInterface;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class FeedbackTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    public function testFeedbackRequiresAuthentication(): void
    {
        self::createClient()->request('POST', '/api/feedback', [
            'json' => ['message' => 'This is my feedback message'],
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testFeedbackWithValidMessage(): void
    {
        $user = UserFactory::createOne();

        $this->createAuthenticatedClient($user)->request('POST', '/api/feedback', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['message' => 'This is my feedback message about the new system.'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');
        self::assertJsonContains([
            'success' => true,
            'message' => 'Merci pour votre retour !',
        ]);
    }

    public function testFeedbackSendsEmail(): void
    {
        $user = UserFactory::createOne([
            'username' => 'testuser',
            'email' => 'testuser@example.com',
        ]);

        $this->createAuthenticatedClient($user)->request('POST', '/api/feedback', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['message' => 'This is my feedback message for email test.'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertEmailHeaderSame($email, 'Subject', 'Feedback utilisateur - By Night');
        self::assertEmailHtmlBodyContains($email, 'testuser');
        self::assertEmailHtmlBodyContains($email, 'testuser@example.com');
        self::assertEmailHtmlBodyContains($email, 'This is my feedback message for email test.');
    }

    public function testFeedbackWithEmptyMessageFails(): void
    {
        $user = UserFactory::createOne();

        $this->createAuthenticatedClient($user)->request('POST', '/api/feedback', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['message' => ''],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testFeedbackWithTooShortMessageFails(): void
    {
        $user = UserFactory::createOne();

        $this->createAuthenticatedClient($user)->request('POST', '/api/feedback', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['message' => 'Short'],
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

    public function testFeedbackWithMissingMessageFails(): void
    {
        $user = UserFactory::createOne();

        $this->createAuthenticatedClient($user)->request('POST', '/api/feedback', [
            'headers' => ['Accept' => 'application/json'],
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    private function createAuthenticatedClient(UserInterface|Proxy $user): Client
    {
        $user = $user instanceof Proxy ? $user->_real() : $user;
        $client = self::createClient();
        $client->loginUser($user);

        return $client;
    }
}
