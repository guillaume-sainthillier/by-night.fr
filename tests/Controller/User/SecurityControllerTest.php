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
use App\Factory\WebauthnKeyFactory;
use App\Tests\AppWebTestCase;
use App\Tests\Security\LogsInByRememberMe;
use App\Tests\Security\TwoFactor\SoftwarePasskey;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\DataProvider;

use function Zenstruck\Foundry\Persistence\refresh_all;

/**
 * The second factors of the logged-in member, in the "Sécurité" tab of their account and the pages under
 * /profile/securite: recovery codes first, then an authenticator app and/or passkeys
 */
final class SecurityControllerTest extends AppWebTestCase
{
    use LogsInByRememberMe;

    #[DataProvider('provideSecurityPages')]
    public function testTheSecurityPagesAskAnonymousVisitorsToLogIn(string $url): void
    {
        $client = self::createClient();

        $client->request('GET', $url);

        self::assertResponseRedirects('/login');
    }

    /**
     * A remember-me cookie never asked for the second factor: a stolen one would read new recovery codes, or set up an
     * app of the thief's
     */
    #[DataProvider('provideSetupPages')]
    public function testTheSetupPagesAskARememberedSessionToLogInAgain(string $url): void
    {
        $client = self::createClient();
        self::logInByRememberMe($client, self::createRememberedMember(['backupCodes' => ['AAAA-BBBB']]));

        $client->request('GET', '/profile/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#security .alert-info', 'reconnectez-vous');

        $client->request('GET', $url);

        self::assertResponseRedirects('/login');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSetupPages(): iterable
    {
        yield 'recovery codes' => ['/profile/securite/codes-de-secours'];
        yield 'authenticator app' => ['/profile/securite/application'];
        yield 'new passkey' => ['/profile/securite/cles/nouvelle'];
    }

    /**
     * The buttons of the "Sécurité" tab, sent with their CSRF token by a session a remember-me cookie opened: nothing
     * is turned off
     */
    public function testARememberedSessionTurnsNoSecondFactorOff(): void
    {
        $client = self::createClient();
        $secret = TOTP::generate()->getSecret();
        $member = self::createRememberedMember(['googleAuthenticatorSecret' => $secret, 'backupCodes' => ['AAAA-BBBB']]);
        WebauthnKeyFactory::createOne(['user' => $member, 'name' => 'iPhone']);
        refresh_all();
        self::logInByRememberMe($client, $member);

        foreach (['Désactiver', 'Supprimer les codes', 'Supprimer iPhone'] as $button) {
            $client->request('GET', '/profile/edit');
            $client->submitForm($button);

            self::assertResponseRedirects('/login');
        }

        self::assertSame($secret, $member->getGoogleAuthenticatorSecret());
        self::assertSame(['AAAA-BBBB'], $member->getBackupCodes());
        WebauthnKeyFactory::assert()->count(1);
    }

    /**
     * The login a remembered session is sent to is a whole one, second factor included, then leads to the page
     */
    public function testARememberedMemberLogsInAgainWithTheirSecondFactor(): void
    {
        $client = self::createClient();
        $member = self::createRememberedMember([
            'googleAuthenticatorSecret' => TOTP::generate()->getSecret(),
            'backupCodes' => ['AAAA-BBBB'],
        ]);
        self::logInByRememberMe($client, $member);

        $client->request('GET', '/profile/securite/codes-de-secours');
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        $client->submitForm('Se connecter', ['username' => (string) $member->getEmail(), 'password' => self::REMEMBERED_PASSWORD]);
        self::assertResponseRedirects('/2fa');
        self::passTheAuthenticatorApp($client, $member);

        self::assertResponseRedirects('https://by-night.test/profile/securite/codes-de-secours');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    /**
     * Disconnecting a network logs the member in again to reload their roles: a session a remember-me cookie opened
     * stays one, not a session logged in afresh
     */
    public function testDisconnectingANetworkKeepsARememberedSessionRemembered(): void
    {
        $client = self::createClient();
        self::logInByRememberMe($client, self::createRememberedMember(['backupCodes' => ['AAAA-BBBB']]));
        $client->request('GET', '/profile/edit');

        $client->request('POST', '/social/facebook/deconnexion');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/profile/securite/codes-de-secours');
        self::assertResponseRedirects('/login');
        $client->request('GET', '/profile/edit');
        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSecurityPages(): iterable
    {
        yield 'overview' => ['/profile/edit'];
        yield 'recovery codes' => ['/profile/securite/codes-de-secours'];
        yield 'authenticator app' => ['/profile/securite/application'];
        yield 'new passkey' => ['/profile/securite/cles/nouvelle'];
    }

    public function testTheSecurityTabShowsEveryFactorOff(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->create());

        $client->request('GET', '/profile/edit');

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextSame('#security .badge', 'Non configurés');
        self::assertAnySelectorTextSame('#security .badge', 'Désactivée');
        self::assertAnySelectorTextSame('#security .badge', '0 clé');
        self::assertSelectorExists('.account-tabs a[href="#security"]');
    }

    public function testTheRecoveryCodesAreSavedOnceConfirmed(): void
    {
        $client = self::createClient();
        $member = UserFactory::new()->create();
        $client->loginUser($member);

        $crawler = $client->request('GET', '/profile/securite/codes-de-secours');
        $codes = $crawler->filter('ol[aria-label="Vos codes de secours"] li')->each(static fn ($li): string => trim($li->text()));
        self::assertCount(10, $codes);
        self::assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $codes[0]);

        // The same codes until confirmed, so the downloaded file matches the page
        $client->request('GET', '/profile/securite/codes-de-secours/telecharger');
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=utf-8');
        self::assertStringContainsString($codes[9], (string) $client->getResponse()->getContent());

        // Not without the confirmation
        $client->request('GET', '/profile/securite/codes-de-secours');
        $client->submitForm('Enregistrer les codes');
        self::assertNull($member->getBackupCodes());

        $client->submitForm('Enregistrer les codes', ['recovery_codes[saved]' => true]);

        self::assertResponseRedirects('/profile/edit#security');
        self::assertSame($codes, $member->getBackupCodes());
        // Confirmed: no longer downloadable
        $client->request('GET', '/profile/securite/codes-de-secours/telecharger');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheRecoveryCodesAreDeleted(): void
    {
        $client = self::createClient();
        $member = UserFactory::new()->create(['backupCodes' => ['AAAA-BBBB', 'CCCC-DDDD']]);
        $client->loginUser($member);

        $client->request('GET', '/profile/edit');
        $client->submitForm('Supprimer les codes');

        self::assertResponseRedirects('/profile/edit#security');
        self::assertNull($member->getBackupCodes());
        $client->followRedirect();
        self::assertSelectorNotExists('form[action$="/codes-de-secours/supprimer"]');
    }

    public function testTheRecoveryCodesAreNotDeletedWithoutTheCsrfToken(): void
    {
        $client = self::createClient();
        $member = UserFactory::new()->create(['backupCodes' => ['AAAA-BBBB']]);
        $client->loginUser($member);

        $client->request('POST', '/profile/securite/codes-de-secours/supprimer');

        self::assertFalse($client->getResponse()->isSuccessful());
        self::assertSame(['AAAA-BBBB'], $member->getBackupCodes());
    }

    public function testTheAuthenticatorAppNeedsTheRecoveryCodesFirst(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->create());

        $client->request('GET', '/profile/securite/application');

        self::assertResponseRedirects('/profile/securite/codes-de-secours');
    }

    public function testTheAuthenticatorAppIsOnOnceItsFirstCodeIsRight(): void
    {
        $client = self::createClient();
        $member = UserFactory::new()->create(['backupCodes' => ['AAAA-BBBB']]);
        $client->loginUser($member);

        $crawler = $client->request('GET', '/profile/securite/application');
        self::assertSelectorExists('img[src^="data:image/svg+xml;base64,"]');
        self::assertSelectorExists('.otp[data-otp-autosubmit] input.otp-input[name="totp_activation[code]"]');
        $secret = str_replace(' ', '', $crawler->filter('code')->text());

        $client->submitForm('Activer', ['totp_activation[code]' => '000000']);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($member->getGoogleAuthenticatorSecret());

        // The same secret until it is confirmed: the app scanned at the first display still answers
        $client->submitForm('Activer', ['totp_activation[code]' => TOTP::createFromSecret($secret)->now()]);

        self::assertResponseRedirects('/profile/edit#security');
        self::assertSame($secret, $member->getGoogleAuthenticatorSecret());
    }

    public function testTheCodesOfTheAuthenticatorAppSetupAreRateLimited(): void
    {
        $client = self::createClient();
        $client->disableReboot();

        $member = UserFactory::new()->create(['backupCodes' => ['AAAA-BBBB']]);
        $client->loginUser($member);

        $crawler = $client->request('GET', '/profile/securite/application');
        $secret = str_replace(' ', '', $crawler->filter('code')->text());
        for ($i = 0; $i < 5; ++$i) {
            $client->submitForm('Activer', ['totp_activation[code]' => '00000' . $i]);
        }

        $client->submitForm('Activer', ['totp_activation[code]' => TOTP::createFromSecret($secret)->now()]);

        self::assertResponseRedirects('/profile/securite/application');
        self::assertNull($member->getGoogleAuthenticatorSecret());
    }

    public function testTheAuthenticatorAppIsTurnedOff(): void
    {
        $client = self::createClient();
        $member = UserFactory::new()->create(['googleAuthenticatorSecret' => TOTP::generate()->getSecret()]);
        $client->loginUser($member);

        $client->request('GET', '/profile/edit');
        $client->submitForm('Désactiver');

        self::assertResponseRedirects('/profile/edit#security');
        self::assertNull($member->getGoogleAuthenticatorSecret());
    }

    public function testAPasskeyIsRegistered(): void
    {
        $client = self::createClient();
        $member = UserFactory::new()->create(['backupCodes' => ['AAAA-BBBB']]);
        $client->loginUser($member);

        $passkey = new SoftwarePasskey('https://by-night.test');
        $crawler = $client->request('GET', '/profile/securite/cles/nouvelle');
        $options = json_decode((string) $crawler->filter('form.js-webauthn')->attr('data-webauthn-options'), true);
        // Bound to the account id, never to the e-mail, which can change
        self::assertSame(rtrim(strtr(base64_encode((string) $member->getId()), '+/', '-_'), '='), $options['user']['id']);

        $client->submitForm('Créer la clé d’accès', [
            'passkey_registration[name]' => 'iPhone',
            'passkey_registration[credential]' => $passkey->register((string) json_encode($options)),
        ]);

        self::assertResponseRedirects('/profile/edit#security');
        $key = WebauthnKeyFactory::find(['name' => 'iPhone']);
        self::assertSame($member->getId(), $key->getUser()?->getId());
        self::assertSame($passkey->credentialId, $key->publicKeyCredentialId);
    }

    public function testAForgedPasskeyRegistrationIsRefused(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->create(['backupCodes' => ['AAAA-BBBB']]));

        $client->request('GET', '/profile/securite/cles/nouvelle');
        // Answering options made up by the attacker, not the challenge the page stored
        $client->submitForm('Créer la clé d’accès', [
            'passkey_registration[name]' => 'iPhone',
            'passkey_registration[credential]' => new SoftwarePasskey('https://by-night.test')->register((string) json_encode(['challenge' => 'Zm9yZ2Vk'])),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'n\'a pas pu être vérifiée');
        WebauthnKeyFactory::assert()->empty();
    }

    public function testTwoPasskeysCannotShareAName(): void
    {
        $client = self::createClient();
        $member = UserFactory::new()->create(['backupCodes' => ['AAAA-BBBB']]);
        WebauthnKeyFactory::createOne(['user' => $member, 'name' => 'iPhone']);
        refresh_all();
        $client->loginUser($member);

        $crawler = $client->request('GET', '/profile/securite/cles/nouvelle');
        $client->submitForm('Créer la clé d’accès', [
            'passkey_registration[name]' => 'iPhone',
            'passkey_registration[credential]' => new SoftwarePasskey('https://by-night.test')->register((string) $crawler->filter('form.js-webauthn')->attr('data-webauthn-options')),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'déjà une clé d\'accès de ce nom');
        WebauthnKeyFactory::assert()->count(1);
    }

    public function testAPasskeyIsRenamedAndDeleted(): void
    {
        $client = self::createClient();
        $member = UserFactory::new()->create();
        $key = WebauthnKeyFactory::createOne(['user' => $member, 'name' => 'iPhone']);
        refresh_all();
        $client->loginUser($member);

        $client->request('GET', '/profile/securite/cles/' . $key->getUuid()->toString());
        $client->submitForm('Enregistrer', ['passkey[name]' => 'iPhone pro']);
        self::assertResponseRedirects('/profile/edit#security');
        self::assertSame('iPhone pro', $key->getName());

        $client->request('GET', '/profile/edit');
        $client->submitForm('Supprimer iPhone pro');

        self::assertResponseRedirects('/profile/edit#security');
        WebauthnKeyFactory::assert()->empty();
    }

    public function testThePasskeyOfAnotherUserIsNotFound(): void
    {
        $client = self::createClient();
        $key = WebauthnKeyFactory::createOne();
        $client->loginUser(UserFactory::new()->create());

        $client->request('GET', '/profile/securite/cles/' . $key->getUuid()->toString());
        self::assertResponseStatusCodeSame(404);

        // Without the CSRF token of a page showing it, refused before the passkey is even looked for
        $client->request('POST', '/profile/securite/cles/' . $key->getUuid()->toString() . '/supprimer');
        self::assertFalse($client->getResponse()->isSuccessful());
        WebauthnKeyFactory::assert()->count(1);
    }
}
