<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\User;

use App\Entity\User;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserControllerTest extends WebTestCase
{
    public function testAMemberWithoutAnyEventIsNotIndexable(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['username' => 'lurker']);

        $client->request('GET', $this->profilePath($user));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
    }

    public function testAMemberWithEventsInTheirCalendarIsIndexable(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['username' => 'active']);
        UserEventFactory::createOne(['user' => $user]);

        $client->request('GET', $this->profilePath($user));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('meta[name="robots"]');
    }

    private function profilePath(User $user): string
    {
        return \sprintf('/membres/%s--%d', $user->getSlug(), $user->getId());
    }
}
