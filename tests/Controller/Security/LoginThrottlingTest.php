<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Security;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function Zenstruck\Foundry\Persistence\save;

/**
 * Five failed logins a minute for an account, from wherever they come, then even the right
 * password waits.
 */
final class LoginThrottlingTest extends WebTestCase
{
    public function testTheRightPasswordLogsIn(): void
    {
        $client = $this->createClientFromAnIpOfItsOwn();
        $email = $this->createMember('le-bon-mot-de-passe');

        $this->login($client, $email, 'le-bon-mot-de-passe');

        self::assertResponseRedirects();
        self::assertNotNull(self::getContainer()->get('security.untracked_token_storage')->getToken());
    }

    public function testLoginsAreRefusedAfterFiveFailuresInAMinute(): void
    {
        $client = $this->createClientFromAnIpOfItsOwn();
        $email = $this->createMember('le-bon-mot-de-passe');

        for ($i = 0; $i < 5; ++$i) {
            $this->login($client, $email, 'un-mauvais-mot-de-passe');
            $client->followRedirect();
            self::assertSelectorTextContains('.alert-danger', 'Identifiants invalides');
        }

        $this->login($client, $email, 'le-bon-mot-de-passe');
        $client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'veuillez réessayer dans 1 minute');
        self::assertNull(self::getContainer()->get('security.untracked_token_storage')->getToken());
    }

    /**
     * The attempts are counted per IP address too, in a pool that outlives the test: a run
     * must not be throttled by the previous one.
     */
    public function testTheAttemptsAreCountedPerAccountWhateverTheIpAddress(): void
    {
        $email = $this->createMember('le-bon-mot-de-passe');
        $other = $this->createMember('un-autre-mot-de-passe');

        for ($i = 0; $i < 5; ++$i) {
            self::ensureKernelShutdown();
            $this->login($this->createClientFromAnIpOfItsOwn(), $email, 'un-mauvais-mot-de-passe');
        }

        self::ensureKernelShutdown();
        $client = $this->createClientFromAnIpOfItsOwn();
        $this->login($client, $email, 'le-bon-mot-de-passe');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'veuillez réessayer dans 1 minute');

        // Another account from the same address is not held back by these failures
        $this->login($client, $other, 'un-autre-mot-de-passe');
        self::assertResponseRedirects();
        self::assertNotNull(self::getContainer()->get('security.untracked_token_storage')->getToken());
    }

    private function createClientFromAnIpOfItsOwn(): KernelBrowser
    {
        $client = self::createClient();
        $client->setServerParameter('REMOTE_ADDR', \sprintf('10.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(1, 254)));

        return $client;
    }

    private function createMember(string $password): string
    {
        $user = UserFactory::createOne(['verified' => true]);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));
        save($user);

        return (string) $user->getEmail();
    }

    private function login(KernelBrowser $client, string $email, string $password): void
    {
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('form[action="/login"]')->form();
        $form['username'] = $email;
        $form['password'] = $password;
        $client->submit($form);
    }
}
