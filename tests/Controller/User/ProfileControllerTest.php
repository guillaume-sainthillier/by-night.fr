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
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function Zenstruck\Foundry\Persistence\refresh;

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

    public function testAnEmptyNewPasswordIsRejected(): void
    {
        $client = self::createClient();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user = UserFactory::createOne(['password' => $hasher->hashPassword(new User(), 'ancien-mot-de-passe')]);
        $client->loginUser($user);
        $passwordHash = $user->getPassword();

        $client->request('GET', '/profile/edit');
        $client->submitForm('Mettre à jour le mot de passe', [
            'change_password_form[currentPassword]' => 'ancien-mot-de-passe',
            'change_password_form[plainPassword][first]' => '',
            'change_password_form[plainPassword][second]' => '',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        refresh($user);
        self::assertSame($passwordHash, $user->getPassword());
    }
}
