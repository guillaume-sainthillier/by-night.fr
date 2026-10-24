<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Security\TwoFactor;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Factory\WebauthnKeyFactory;
use App\Tests\AppWebTestCase;
use App\Tests\Security\LogsInByRememberMe;
use OTPHP\TOTP;
use Override;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

use function Zenstruck\Foundry\Persistence\refresh_all;
use function Zenstruck\Foundry\Persistence\save;

/**
 * A login with the password asks for the second factor of a User who turned one on (config/packages/scheb_2fa.yaml):
 * the code of the authenticator app, a recovery code or a passkey, on the /2fa form.
 */
final class TwoFactorLoginTest extends AppWebTestCase
{
    use LogsInByRememberMe;

    private const string PASSWORD = 'mot-de-passe';

    private KernelBrowser $client;

    /** One per test: the login throttling counts the attempts per address, in a pool that outlives the test */
    private string $email;

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->email = \sprintf('membre-%s@example.com', bin2hex(random_bytes(6)));
    }

    public function testAUserWithoutSecondFactorLogsInWithTheirPasswordOnly(): void
    {
        $this->createUser();

        $this->logInWithPassword();

        self::assertResponseRedirects('/espace-perso/mes-soirees');
    }

    public function testTheCodeOfTheAuthenticatorAppCompletesTheLogin(): void
    {
        $user = $this->createUser(['googleAuthenticatorSecret' => TOTP::generate()->getSecret()]);

        $this->logInWithPassword();
        self::assertResponseRedirects('/2fa');

        // Only between the password and the second factor: the referrer pages stay closed
        $this->client->request('GET', '/espace-perso/mes-soirees');
        self::assertResponseRedirects('/2fa');

        $crawler = $this->client->request('GET', '/2fa');
        self::assertSelectorExists('.otp[data-bs-toggle="otp"] input.otp-input[maxlength="6"][autocomplete="one-time-code"]');
        // No passkey on the account: no passkey form
        self::assertSelectorNotExists('form.js-webauthn');

        $this->client->submit($this->form($crawler, 'totp'), ['_auth_code' => $this->totpCode($user)]);

        // On to the page asked for before the second factor
        self::assertResponseRedirects('https://by-night.test/espace-perso/mes-soirees');
        $this->assertLoggedIn();
    }

    /**
     * The page the member logged in from (LoginTargetPath) is kept for after the second factor
     */
    public function testTheSecondFactorLeadsBackToThePageTheLoginCameFrom(): void
    {
        $user = $this->createUser(['googleAuthenticatorSecret' => TOTP::generate()->getSecret()]);

        $this->client->request('GET', '/login?_target_path=' . rawurlencode('/toulouse/soiree/concert--42#participer'));
        $this->client->submitForm('Se connecter', ['username' => $this->email, 'password' => self::PASSWORD]);
        self::assertResponseRedirects('/2fa');

        $crawler = $this->client->request('GET', '/2fa');
        $this->client->submit($this->form($crawler, 'totp'), ['_auth_code' => $this->totpCode($user)]);

        self::assertResponseRedirects('/toulouse/soiree/concert--42#participer');
    }

    public function testAWrongCodeKeepsTheUserOnTheSecondFactor(): void
    {
        $this->createUser(['googleAuthenticatorSecret' => TOTP::generate()->getSecret()]);

        $this->logInWithPassword();
        $crawler = $this->client->request('GET', '/2fa');
        $this->client->submit($this->form($crawler, 'totp'), ['_auth_code' => '000000']);

        self::assertResponseRedirects('/2fa');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', "Ce code n'est pas valide");
        self::assertSelectorExists('form[action="/2fa_check"]');
    }

    public function testARecoveryCodeCompletesTheLoginOnce(): void
    {
        $user = $this->createUser([
            'googleAuthenticatorSecret' => TOTP::generate()->getSecret(),
            'backupCodes' => ['AAAA-BBBB', 'CCCC-DDDD', 'EEEE-FFFF'],
        ]);

        $this->logInWithPassword();
        $crawler = $this->client->request('GET', '/2fa');
        $this->client->submit($this->form($crawler, 'backup_code'), ['_auth_code' => 'CCCC-DDDD']);

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        // Still a JSON list once a code in the middle is gone
        self::assertSame(['AAAA-BBBB', 'EEEE-FFFF'], $user->getBackupCodes());
    }

    public function testAPasskeyCompletesTheLogin(): void
    {
        $user = $this->createUser();
        $passkey = $this->registerPasskey($user);
        $this->client->request('GET', '/logout');

        $this->logInWithPassword();
        self::assertResponseRedirects('/2fa');
        $crawler = $this->client->request('GET', '/2fa');
        // A passkey alone: neither the TOTP form nor its separator
        self::assertSelectorNotExists('input[name="auth_type"][value="totp"]');

        $form = $this->form($crawler, 'webauthn');
        $options = (string) $crawler->filter('form.js-webauthn')->attr('data-webauthn-options');
        $this->client->submit($form, ['_auth_code' => $passkey->authenticate($options, (string) $user->getId())]);

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        $key = $user->getWebauthnKeys()->first();
        self::assertNotFalse($key);
        self::assertNotNull($key->getLastUsedAt());
        self::assertSame(1, $key->counter);
    }

    public function testAPasskeyAnswerForAnotherChallengeIsRefused(): void
    {
        $user = $this->createUser();
        $passkey = $this->registerPasskey($user);
        $this->client->request('GET', '/logout');

        $this->logInWithPassword();
        $staleOptions = (string) $this->client->request('GET', '/2fa')->filter('form.js-webauthn')->attr('data-webauthn-options');
        // Each display of the form sends a new challenge: the answer to the previous one no longer counts
        $crawler = $this->client->request('GET', '/2fa');
        $this->client->submit($this->form($crawler, 'webauthn'), ['_auth_code' => $passkey->authenticate($staleOptions, (string) $user->getId())]);

        self::assertResponseRedirects('/2fa');
    }

    /**
     * A TOTP code typed in the passkey form, or the empty field of a cancelled prompt, would make the library throw a
     * TypeError: it is a wrong code instead
     */
    public function testAMalformedPasskeyAnswerIsAWrongCode(): void
    {
        $user = $this->createUser(['googleAuthenticatorSecret' => TOTP::generate()->getSecret()]);
        WebauthnKeyFactory::createOne(['user' => $user]);
        refresh_all();

        $this->logInWithPassword();
        foreach (['123456', '', '{"id": "x"}', '[1, 2]'] as $answer) {
            $crawler = $this->client->request('GET', '/2fa');
            $this->client->submit($this->form($crawler, 'webauthn'), ['_auth_code' => $answer]);

            self::assertResponseRedirects('/2fa');
        }
    }

    public function testRepeatedWrongCodesLockTheSecondFactorForAWhile(): void
    {
        // The limiters remember the failures in memory, kept as long as the kernel
        $this->client->disableReboot();
        $user = $this->createUser(['googleAuthenticatorSecret' => TOTP::generate()->getSecret()]);

        $this->logInWithPassword();
        for ($i = 0; $i < 5; ++$i) {
            $crawler = $this->client->request('GET', '/2fa');
            $this->client->submit($this->form($crawler, 'totp'), ['_auth_code' => '00000' . $i]);
        }

        // Even the right code is refused once the attempts are spent
        $crawler = $this->client->request('GET', '/2fa');
        $this->client->submit($this->form($crawler, 'totp'), ['_auth_code' => $this->totpCode($user)]);

        self::assertResponseRedirects('/2fa');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Trop de codes essayés');
    }

    /**
     * The providers of a login are frozen with its password: a secret removed meanwhile (from another session) makes
     * the check fail, not a 500
     */
    public function testATotpRemovedDuringTheLoginIsAFailureNotAnError(): void
    {
        $user = $this->createUser(['googleAuthenticatorSecret' => TOTP::generate()->getSecret()]);

        $this->logInWithPassword();
        $crawler = $this->client->request('GET', '/2fa');
        $user = UserFactory::find(['email' => $this->email]);
        $user->setGoogleAuthenticatorSecret(null);
        save($user);
        $this->client->submit($this->form($crawler, 'totp'), ['_auth_code' => '123456']);

        self::assertResponseRedirects('/2fa');
    }

    /**
     * The remember-me cookie is issued once the second factor is passed, and logs in again once the session is gone
     */
    public function testTheRememberMeCookieOfALoginWithItsSecondFactorLogsInAgain(): void
    {
        self::logInByRememberMe($this->client, self::createRememberedMember(['googleAuthenticatorSecret' => TOTP::generate()->getSecret()]));

        $this->client->request('GET', '/profile/edit');

        self::assertResponseIsSuccessful();
    }

    /**
     * A cookie issued before the second factor was turned on would skip it: the cookies are signed with the secret of
     * the app (config/packages/security.yaml), it no longer logs in
     */
    public function testTurningOnTheAuthenticatorAppInvalidatesTheRememberMeCookies(): void
    {
        $member = self::createRememberedMember();
        self::logInRemembered($this->client, $member);

        // From another session: the one the login rebooted the kernel for no longer holds the member
        $member = UserFactory::find(['email' => $member->getEmail()]);
        $member->setGoogleAuthenticatorSecret(TOTP::generate()->getSecret());
        save($member);
        self::forgetSession($this->client);
        $this->client->request('GET', '/profile/edit');

        self::assertResponseRedirects('/login');
    }

    /**
     * Signed with the passkeys of the account too
     */
    public function testAddingAPasskeyInvalidatesTheRememberMeCookies(): void
    {
        $member = self::createRememberedMember();
        self::logInRemembered($this->client, $member);

        WebauthnKeyFactory::createOne(['user' => $member]);
        self::forgetSession($this->client);
        $this->client->request('GET', '/profile/edit');

        self::assertResponseRedirects('/login');
    }

    public function testCancellingTheSecondFactorLogsOut(): void
    {
        $this->createUser(['googleAuthenticatorSecret' => TOTP::generate()->getSecret()]);

        $this->logInWithPassword();
        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/espace-perso/mes-soirees');

        self::assertResponseRedirects('/login');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createUser(array $attributes = []): User
    {
        $hasher = self::getContainer()->get(PasswordHasherFactoryInterface::class)->getPasswordHasher(User::class);

        return UserFactory::new()->create([
            'email' => $this->email,
            'password' => $hasher->hash(self::PASSWORD),
            ...$attributes,
        ]);
    }

    private function logInWithPassword(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['username' => $this->email, 'password' => self::PASSWORD]);
    }

    /**
     * A passkey registered through the security page, the user logged in without a second factor
     */
    private function registerPasskey(User $user): SoftwarePasskey
    {
        $user->setBackupCodes(['AAAA-BBBB']);
        save($user);
        $this->client->loginUser($user);

        $passkey = new SoftwarePasskey('https://by-night.test');
        $crawler = $this->client->request('GET', '/profile/securite/cles/nouvelle');
        $options = (string) $crawler->filter('form.js-webauthn')->attr('data-webauthn-options');
        $this->client->submitForm('Créer la clé d’accès', [
            'passkey_registration[name]' => 'iPhone',
            'passkey_registration[credential]' => $passkey->register($options),
        ]);
        self::assertResponseRedirects('/profile/edit#security');

        return $passkey;
    }

    /**
     * A full login, past its second factor: no longer the TwoFactorToken scheb holds in between
     */
    private function assertLoggedIn(): void
    {
        $token = self::getContainer()->get('security.untracked_token_storage')->getToken();
        self::assertNotNull($token);
        self::assertNotInstanceOf(TwoFactorTokenInterface::class, $token);
    }

    private function totpCode(User $user): string
    {
        return TOTP::createFromSecret((string) $user->getGoogleAuthenticatorSecret())->now();
    }

    private function form(Crawler $crawler, string $authType): Form
    {
        return $crawler->filter('input[name="auth_type"][value="' . $authType . '"]')->closest('form')->form();
    }
}
