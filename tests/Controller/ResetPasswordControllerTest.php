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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[RequiresPhpExtension('mjml')]
final class ResetPasswordControllerTest extends WebTestCase
{
    public function testAnEmptyEmailIsRejectedInFrench(): void
    {
        $client = self::createClient();

        $client->request('GET', '/mot-de-passe-perdu');
        $client->submitForm('Réinitialiser le mot de passe', [
            'reset_password_request_form[email]' => '',
        ]);

        self::assertResponseIsUnprocessable();
        self::assertAnySelectorTextContains('form', 'Veuillez saisir votre adresse e-mail.');
    }

    public function testAnInvalidResetLinkIsExplainedInFrench(): void
    {
        $client = self::createClient();
        $client->followRedirects();

        $client->request('GET', '/mot-de-passe-perdu/reset/not-a-valid-token');

        // The flash used to show the bundle's English reason: "The reset password link is invalid…"
        self::assertSelectorTextContains('.alert-danger', "Le lien de réinitialisation du mot de passe n'est pas valide.");
    }

    public function testTheResetEmailStatesHowLongTheLinkIsValid(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'forgetful@example.com']);

        $client->request('GET', '/mot-de-passe-perdu');
        $client->submitForm('Réinitialiser le mot de passe', [
            'reset_password_request_form[email]' => 'forgetful@example.com',
        ]);

        self::assertResponseRedirects('/mot-de-passe-perdu/verifier-email');
        self::assertEmailCount(1);
        self::assertEmailHeaderSame(self::getMailerMessage(), 'Subject', 'Réinitialisation de votre mot de passe - By Night');
        // The token lives 3600 seconds: the e-mail used to read that as a timestamp and say "2 heure(s)".
        self::assertEmailHtmlBodyContains(self::getMailerMessage(), 'Ce lien est valable 1 heure.');
    }
}
