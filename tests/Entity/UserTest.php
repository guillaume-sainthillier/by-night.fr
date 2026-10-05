<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testTheProfileIsCompleteWithAPictureTheNamesAPresentationAndAVerifiedAddress(): void
    {
        $user = new User();
        self::assertSame(0, $user->getProfileCompletion());

        $user->setFirstname('Ada')->setLastname('');
        // An empty name (older rows) is not filled
        self::assertSame(20, $user->getProfileCompletion());

        $user->setLastname('Lovelace')->setDescription('Amatrice de jazz')->setVerified(true);
        $user->getImage()->setName('ada.jpg');
        self::assertSame(100, $user->getProfileCompletion());
    }
}
