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
use App\Factory\CityFactory;
use App\Factory\UserFactory;
use App\Form\Type\DeleteAccountFormType;
use App\Tests\AppWebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function Zenstruck\Foundry\Persistence\refresh;

final class ProfileControllerTest extends AppWebTestCase
{
    public function testAnInvalidDeletionShowsItsErrorOnTheProfile(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        // An expired CSRF token makes the deletion form invalid
        $client->request('POST', '/profile/delete', ['delete_account_form' => ['delete_events' => '1', '_token' => 'expired']]);

        self::assertResponseRedirects('/profile/edit#delete');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame(1, UserFactory::count(['id' => $user->getId()]), 'The account is kept');
    }

    public function testTheDeletionNeedsTheConfirmationWord(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        $client->request('GET', '/profile/edit');
        $client->submitForm('Supprimer mon compte', ['delete_account_form[confirmation]' => 'supprimer']);

        self::assertResponseRedirects('/profile/edit#delete');
        self::assertSame(1, UserFactory::count(['id' => $user->getId()]), 'The account is kept');
    }

    public function testTheConfirmedDeletionRemovesTheAccount(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $userId = $user->getId();
        $client->loginUser($user);

        $client->request('GET', '/profile/edit');
        $client->submitForm('Supprimer mon compte', ['delete_account_form[confirmation]' => DeleteAccountFormType::CONFIRMATION]);

        self::assertResponseRedirects('/');
        self::assertSame(0, UserFactory::count(['id' => $userId]));
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

    public function testAWrongCurrentPasswordSaysSo(): void
    {
        $client = self::createClient();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user = UserFactory::createOne(['password' => $hasher->hashPassword(new User(), 'ancien-mot-de-passe')]);
        $client->loginUser($user);

        $client->request('GET', '/profile/edit');
        $client->submitForm('Mettre à jour le mot de passe', [
            'change_password_form[currentPassword]' => 'mauvais-mot-de-passe',
            'change_password_form[plainPassword][first]' => 'Nouveau2026',
            'change_password_form[plainPassword][second]' => 'Nouveau2026',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('#password .invalid-feedback', 'Le mot de passe actuel est incorrect.');
    }

    public function testAMemberChoosesTheirCity(): void
    {
        $client = self::createClient();
        CityFactory::toulouse()->create();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        $client->request('GET', '/profile/edit');
        $client->submitForm('Enregistrer les modifications', [
            'profile_form[city][name]' => 'Toulouse (France)',
            'profile_form[city][slug]' => 'toulouse',
        ]);

        self::assertResponseIsSuccessful();
        refresh($user);
        self::assertSame('toulouse', $user->getCity()?->getSlug());
        self::assertInputValueSame('profile_form[city][name]', 'Toulouse (France)');
    }

    public function testEmptyingTheCityRemovesIt(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['city' => CityFactory::toulouse()]);
        $client->loginUser($user);

        $client->request('GET', '/profile/edit');
        $client->submitForm('Enregistrer les modifications', [
            'profile_form[city][name]' => '',
            'profile_form[city][slug]' => '',
        ]);

        self::assertResponseIsSuccessful();
        refresh($user);
        self::assertNull($user->getCity());
    }

    public function testACityTypedWithoutChoosingInTheListIsRejected(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['city' => CityFactory::toulouse()]);
        $client->loginUser($user);

        // Typing in the field empties the slug until a city of the list is chosen
        $client->request('GET', '/profile/edit');
        $client->submitForm('Enregistrer les modifications', [
            'profile_form[city][name]' => 'Saint-Denis',
            'profile_form[city][slug]' => '',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('#profile .invalid-feedback', 'Choisissez une ville dans la liste.');
        refresh($user);
        self::assertSame('toulouse', $user->getCity()?->getSlug(), 'The city is kept');
    }
}
