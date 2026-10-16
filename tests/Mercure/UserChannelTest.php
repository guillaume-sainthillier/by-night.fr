<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Mercure;

use App\Factory\UserFactory;
use App\Mercure\UserChannel;
use App\Tests\AppKernelTestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

final class UserChannelTest extends AppKernelTestCase
{
    public function testTheTwigBridgeSubscribesToTheChannelAndGrantsOnlyIt(): void
    {
        $user = UserFactory::createOne();
        $topic = 'https://by-night.test/users/' . $user->getId();

        $request = Request::create('https://by-night.test/espace-perso/mes-soirees');
        self::getContainer()->get(RequestStack::class)->push($request);

        $twig = self::getContainer()->get(Environment::class);
        $url = $twig->createTemplate('{{ mercure(user_channel(user), {subscribe: user_channel(user)}) }}')
            ->render(['user' => $user]);

        self::assertSame('https://by-night.test/.well-known/mercure?match=' . rawurlencode($topic), html_entity_decode($url));

        /** @var array<string, Cookie> $cookies */
        $cookies = $request->attributes->get('_mercure_authorization_cookies');
        $cookie = $cookies[''];
        self::assertSame('__Secure-mercure_access_token', $cookie->getName());
        self::assertSame('/.well-known/mercure', $cookie->getPath());
        self::assertTrue($cookie->isHttpOnly());

        $claims = json_decode(base64_decode(strtr(explode('.', (string) $cookie->getValue())[1], '-_', '+/'), true), true);
        self::assertSame('https://by-night.fr', $claims['iss']);
        self::assertContains('https://by-night.test/.well-known/mercure', (array) $claims['aud']);
        self::assertSame([[
            'type' => 'https://mercure.rocks/authorization-detail',
            'actions' => ['subscribe'],
            'topics' => [['match' => $topic]],
        ]], $claims['authorization_details']);
    }

    public function testAnUpdateGoesToTheResourceThenTheChannelPrivately(): void
    {
        $user = UserFactory::createOne();
        $channel = new UserChannel('https://by-night.test/');

        $update = $channel->update($user, 'https://by-night.test/espace-perso/imports/1', 'event-import', ['status' => 'checking']);

        self::assertSame(['https://by-night.test/espace-perso/imports/1', 'https://by-night.test/users/' . $user->getId()], $update->getTopics());
        self::assertTrue($update->isPrivate());
        self::assertSame('event-import', $update->getType());
        self::assertSame('{"@id":"https://by-night.test/espace-perso/imports/1","status":"checking"}', $update->getData());
    }
}
