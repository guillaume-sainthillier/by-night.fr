<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Security;

use App\Entity\User;
use App\Factory\UserFactory;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * What a stolen REMEMBERME cookie gives: the member logs in with the form, "Se souvenir de moi" checked (their
 * authenticator app too when they turned it on), then the browser loses its session and keeps the cookie only. Its
 * next request is logged in by the cookie (a RememberMeToken), which never asks for the second factor.
 */
trait LogsInByRememberMe
{
    private const string REMEMBERED_PASSWORD = 'mot-de-passe';

    /**
     * A member whose password is REMEMBERED_PASSWORD. The login throttling counts the attempts per address, in a pool
     * that outlives the test: an address of their own
     *
     * @param array<string, mixed> $attributes
     */
    private static function createRememberedMember(array $attributes = []): User
    {
        $hasher = self::getContainer()->get(PasswordHasherFactoryInterface::class)->getPasswordHasher(User::class);

        return UserFactory::new()->create([
            'email' => \sprintf('membre-%s@example.com', bin2hex(random_bytes(6))),
            'password' => $hasher->hash(self::REMEMBERED_PASSWORD),
            ...$attributes,
        ]);
    }

    private static function logInByRememberMe(KernelBrowser $client, User $member): void
    {
        self::logInRemembered($client, $member);
        self::forgetSession($client);
    }

    /**
     * The login form, then the authenticator app if the member turned it on: the remember-me cookie is only issued
     * once the second factor is passed
     */
    private static function logInRemembered(KernelBrowser $client, User $member): void
    {
        $client->request('GET', '/login');
        $client->submitForm('Se connecter', ['username' => (string) $member->getEmail(), 'password' => self::REMEMBERED_PASSWORD]);
        if ($client->getResponse()->isRedirect('/2fa')) {
            self::passTheAuthenticatorApp($client, $member);
        }

        self::assertNotNull($client->getCookieJar()->get('REMEMBERME'), 'The login form remembers the member by default');
    }

    private static function passTheAuthenticatorApp(KernelBrowser $client, User $member): void
    {
        $crawler = $client->request('GET', '/2fa');
        $form = $crawler->filter('input[name="auth_type"][value="totp"]')->closest('form')?->form();
        self::assertNotNull($form);
        $client->submit($form, ['_auth_code' => TOTP::createFromSecret((string) $member->getGoogleAuthenticatorSecret())->now()]);
    }

    /**
     * The session is gone (expired, or another browser): only the remember-me cookie is left
     */
    private static function forgetSession(KernelBrowser $client): void
    {
        $rememberMe = $client->getCookieJar()->get('REMEMBERME');
        self::assertNotNull($rememberMe);
        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($rememberMe);
    }
}
