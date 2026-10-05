<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A new member signs up with their names and the username they choose, which no other member may already use.
 */
final class RegistrationControllerTest extends WebTestCase
{
    #[RequiresPhpExtension('mjml')]
    public function testANewMemberKeepsTheUsernameTheyChose(): void
    {
        $client = self::createClient();

        $this->signUp($client, 'camille.martin@example.com', 'camille_m');

        self::assertResponseRedirects();
        $user = UserFactory::find(['email' => 'camille.martin@example.com']);
        // As typed, not capitalised
        self::assertSame('camille_m', $user->getUsername());
        self::assertSame('Camille', $user->getFirstname());
        self::assertSame('Martin', $user->getLastname());
    }

    public function testATakenUsernameIsRefused(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['username' => 'camille_m']);

        $this->signUp($client, 'camille.martin@example.com', 'camille_m');

        self::assertResponseIsUnprocessable();
        self::assertAnySelectorTextContains('.invalid-feedback', "Ce nom d'utilisateur est déjà pris.");
        self::assertSame(0, UserFactory::count(['email' => 'camille.martin@example.com']));
    }

    public function testAWeakPasswordIsRefused(): void
    {
        $client = self::createClient();

        $this->signUp($client, 'camille.martin@example.com', 'camille_m', 'motdepasse');

        self::assertResponseIsUnprocessable();
        self::assertAnySelectorTextContains('.invalid-feedback', 'Votre mot de passe doit contenir au moins un chiffre.');
        self::assertSame(0, UserFactory::count(['email' => 'camille.martin@example.com']));
    }

    #[RequiresPhpExtension('mjml')]
    public function testANewMemberGoesBackToThePageTheySignedUpFrom(): void
    {
        $client = self::createClient();

        $this->signUp($client, 'camille.martin@example.com', 'camille_m', url: '/inscription?_target_path=' . rawurlencode('/toulouse/soiree/concert--42#participer'));

        // Logged in at once, back on the event, whose page records the "J'y vais" clicked before signing up
        self::assertResponseRedirects('/toulouse/soiree/concert--42#participer');
    }

    public function testABrokenVerificationLinkLeadsToTheAccountPage(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne(['verified' => false]));

        $client->request('GET', '/verifier-email', ['signature' => 'not-a-signature', 'expires' => time() + 3600]);

        // Where a new link can be sent, not the sign-up form of someone who already has an account
        self::assertResponseRedirects('/profile/edit');
    }

    private function signUp(KernelBrowser $client, string $email, string $username, string $password = 'Motdepasse1', string $url = '/inscription'): void
    {
        $client->request('GET', $url);
        $client->submitForm('Créer mon compte', [
            'registration_form[firstname]' => 'Camille',
            'registration_form[lastname]' => 'Martin',
            'registration_form[username]' => $username,
            'registration_form[email]' => $email,
            'registration_form[plainPassword][first]' => $password,
            'registration_form[plainPassword][second]' => $password,
        ]);
    }
}
