<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Factory\WebauthnKeyFactory;
use App\Tests\AppWebTestCase;
use OTPHP\TOTP;
use Override;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

use function Zenstruck\Foundry\Persistence\refresh_all;

/**
 * A user who lost their second factor asks, from the 2FA form, for a link e-mailed to them that turns it off
 */
final class TwoFactorRecoveryControllerTest extends AppWebTestCase
{
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

    #[RequiresPhpExtension('mjml')]
    public function testTheRecoveryLinkTurnsEveryFactorOffOnce(): void
    {
        $user = $this->createUser();
        WebauthnKeyFactory::createOne(['user' => $user]);
        refresh_all();

        $url = $this->requestRecoveryLink();

        // A GET only asks for a confirmation: a mail scanner prefetching the link does not burn it
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertTrue($user->isGoogleAuthenticatorEnabled());

        $this->client->submitForm('Désactiver la double authentification');

        self::assertResponseRedirects('/login');
        self::assertFalse($user->isGoogleAuthenticatorEnabled());
        self::assertNull($user->getBackupCodes());
        WebauthnKeyFactory::assert()->empty();
        self::assertEmailCount(1);
        self::assertEmailHeaderSame(self::getMailerMessage(), 'Subject', 'La double authentification de votre compte est désactivée - By Night');

        // The password is enough again
        $this->logInWithPassword();
        self::assertResponseRedirects('/espace-perso/mes-soirees');

        // Used: the factors it was signed for are gone
        $this->client->request('GET', $url);
        self::assertSelectorTextContains('.alert-danger', 'n’est plus valable');
    }

    #[RequiresPhpExtension('mjml')]
    public function testATamperedLinkIsRefused(): void
    {
        $this->createUser();

        $url = $this->requestRecoveryLink();
        $this->client->request('GET', str_replace('_state=', '_state=0', $url));

        self::assertSelectorTextContains('.alert-danger', 'n’est plus valable');
        self::assertSelectorNotExists('form button.btn-warning');
    }

    public function testTheRecoveryLinkCannotBeAskedOutsideTheSecondFactor(): void
    {
        $this->client->loginUser($this->createUser());

        // Only from the 2FA form: the access_control of /2fa refuses anyone else
        $this->client->request('POST', '/2fa/recuperation');

        self::assertResponseStatusCodeSame(403);
        self::assertEmailCount(0);
    }

    private function createUser(): User
    {
        $hasher = self::getContainer()->get(PasswordHasherFactoryInterface::class)->getPasswordHasher(User::class);

        return UserFactory::new()->create([
            'email' => $this->email,
            'password' => $hasher->hash(self::PASSWORD),
            'googleAuthenticatorSecret' => TOTP::generate()->getSecret(),
            'backupCodes' => ['AAAA-BBBB'],
        ]);
    }

    private function logInWithPassword(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['username' => $this->email, 'password' => self::PASSWORD]);
    }

    /**
     * The link of the e-mail sent from the 2FA form
     */
    private function requestRecoveryLink(): string
    {
        $this->logInWithPassword();
        $this->client->followRedirect();
        $this->client->submit($this->client->getCrawler()->filter('form[action="/2fa/recuperation"]')->form());

        self::assertResponseRedirects('/2fa');
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', $this->email);
        preg_match('#https?://\S+/connexion/recuperation/\S+#', (string) $email->getTextBody(), $matches);
        self::assertNotEmpty($matches);

        // Another request, as from the mailbox: the e-mails of this one are counted apart
        $this->client->request('GET', '/logout');

        return $matches[0];
    }
}
