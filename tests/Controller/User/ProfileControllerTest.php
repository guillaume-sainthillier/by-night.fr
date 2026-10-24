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
use App\Factory\PublisherFactory;
use App\Factory\UserFactory;
use App\Form\Type\DeleteAccountFormType;
use App\Tests\AppWebTestCase;
use App\Tests\Security\LogsInByRememberMe;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function Zenstruck\Foundry\Persistence\refresh;

final class ProfileControllerTest extends AppWebTestCase
{
    use LogsInByRememberMe;

    public function testAMemberLoggedInCanChangeTheirEmail(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/profile/edit');

        self::assertSelectorExists('input[name="profile_form[email]"]:not([disabled])');
        self::assertSelectorNotExists('#profile a[href^="/login"]');
    }

    /**
     * The address receives the recovery link of the second factor: a stolen remember-me cookie must not redirect it.
     * The rest of the profile is still saved
     */
    public function testARememberedSessionCannotChangeTheEmail(): void
    {
        $client = self::createClient();
        $member = self::createRememberedMember();
        $email = $member->getEmail();
        self::logInByRememberMe($client, $member);

        $crawler = $client->request('GET', '/profile/edit');
        self::assertSelectorExists('input[name="profile_form[email]"][disabled]');
        self::assertSelectorExists('#profile a[href="/login?_target_path=/profile/edit"]');

        // The field sent anyway, as a forged request would
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $values = $form->getPhpValues();
        $values['profile_form']['email'] = 'intrus@example.com';
        $values['profile_form']['firstname'] = 'Lou';
        $client->request('POST', $form->getUri(), $values, $form->getPhpFiles());

        self::assertResponseIsSuccessful();
        self::assertSame($email, $member->getEmail());
        self::assertSame('Lou', $member->getFirstname());
    }

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

    public function testAMemberTurnsTheAlertsOfTheirOutingsOffThenOnAgain(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/profile/edit');
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $alerts = $form['profile_form[outingAlerts]'];
        self::assertInstanceOf(ChoiceFormField::class, $alerts);
        self::assertTrue($alerts->hasValue(), 'on by default');
        $alerts->untick();
        $client->submit($form);

        self::assertResponseIsSuccessful();
        refresh($user);
        self::assertFalse($user->isOutingAlerts());

        $client->submitForm('Enregistrer les modifications', ['profile_form[outingAlerts]' => true]);

        refresh($user);
        self::assertTrue($user->isOutingAlerts());
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

    public function testTheWeekendSelectionIsOffUntilTheMemberAsksForIt(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['city' => CityFactory::toulouse()]);
        $client->loginUser($user);

        $client->request('GET', '/profile/edit');
        self::assertCheckboxNotChecked('profile_form[weekendDigest]');

        $client->submitForm('Enregistrer les modifications', ['profile_form[weekendDigest]' => true]);

        self::assertResponseIsSuccessful();
        refresh($user);
        self::assertTrue($user->isWeekendDigest());
        self::assertNotNull($user->getWeekendDigestSince(), 'The consent is dated');
    }

    public function testTheWeekendSelectionNeedsACity(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        $client->request('GET', '/profile/edit');
        $client->submitForm('Enregistrer les modifications', ['profile_form[weekendDigest]' => true]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('#profile .invalid-feedback', 'Veuillez choisir votre ville pour recevoir la sélection du week-end.');
        // The form left the refused choice on the member in memory: what the database holds is what counts
        self::assertSame(1, UserFactory::count(['id' => $user->getId(), 'weekendDigest' => false]));
    }

    public function testGivingUpTheWeekendSelectionForgetsTheConsent(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['city' => CityFactory::toulouse(), 'weekendDigest' => true]);
        $client->loginUser($user);

        $client->request('GET', '/profile/edit');
        $client->submitForm('Enregistrer les modifications', ['profile_form[weekendDigest]' => false]);

        self::assertResponseIsSuccessful();
        refresh($user);
        self::assertFalse($user->isWeekendDigest());
        self::assertNull($user->getWeekendDigestSince());
    }

    public function testAPublisherOptsOutOfTheNews(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        PublisherFactory::createOne(['owner' => $user]);
        $client->loginUser($user);

        $client->request('GET', '/profile/edit');
        self::assertCheckboxChecked('profile_form[publisherNews]', 'Subscribed by default');
        $client->submitForm('Enregistrer les modifications', ['profile_form[publisherNews]' => false]);

        self::assertResponseIsSuccessful();
        refresh($user);
        self::assertFalse($user->isPublisherNews());
    }

    public function testAMemberWithoutAPublisherIsNotOfferedTheNews(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/profile/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[name="profile_form[publisherNews]"]');
    }

    /**
     * The e-mails a member can turn on or off are in one panel, the organizers' news for organizers only.
     */
    public function testTheEmailsAreChosenInOnePlace(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        PublisherFactory::createOne(['owner' => $user]);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/profile/edit');

        $panel = $crawler->filter('h3:contains("E-mails") + .form-panel');
        self::assertCount(1, $panel);
        self::assertSame(
            ['profile_form[weekendDigest]', 'profile_form[outingAlerts]', 'profile_form[publisherNews]'],
            $panel->filter('input[type="checkbox"]')->each(static fn ($input): ?string => $input->attr('name')),
        );
    }
}
