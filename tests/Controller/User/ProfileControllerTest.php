<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\User;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileControllerTest extends WebTestCase
{
    public function testAnInvalidDeletionShowsItsErrorOnTheProfile(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        // An expired CSRF token makes the deletion form invalid
        $client->request('POST', '/profile/delete', ['form' => ['delete_events' => '1', '_token' => 'expired']]);

        self::assertResponseRedirects('/profile/edit');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame(1, UserFactory::count(['id' => $user->getId()]), 'The account is kept');
    }
}
