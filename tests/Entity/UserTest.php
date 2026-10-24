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
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * Also run against UserRepository's queries (UserRepositoryTest), which must agree.
     *
     * @return iterable<string, array{string, ?string, bool}> the address, the last login, whether it is deliverable
     */
    public static function provideAddresses(): iterable
    {
        yield 'logged in lately' => ['ada@example.com', '-1 month', true];
        yield 'made up for a Facebook login' => ['ada@facebook.fr', '-1 month', false];
        yield 'made up for a Twitter login, in capitals' => ['Ada@Twitter.FR', '-1 month', false];
        yield 'a domain ending the same' => ['ada@notfacebook.fr', '-1 month', true];
        yield 'not logged in since 2018' => ['ada@example.com', '2017-12-31 23:00', false];
        yield 'logged in on the first day of 2018' => ['ada@example.com', '2018-01-01 00:00', true];
        yield 'never logged in' => ['ada@example.com', null, false];
    }

    #[DataProvider('provideAddresses')]
    public function testTheEmailsOnlyGoToAnAddressTheyMayReach(string $email, ?string $lastLogin, bool $deliverable): void
    {
        $user = new User()->setEmail($email)->setLastLogin(null === $lastLogin ? null : new DateTimeImmutable($lastLogin));

        self::assertSame($deliverable, $user->hasDeliverableEmail());
    }

    public function testNoAddressReachesNobody(): void
    {
        self::assertFalse(new User()->hasDeliverableEmail());
        self::assertFalse(new User()->setEmail('')->hasDeliverableEmail());
    }
}
