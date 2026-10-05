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
use App\Tests\AppWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function Zenstruck\Foundry\Persistence\save;

/**
 * A "log in to…" link (J'y vais, comments) names the page it comes from: the login leads back to it.
 */
final class LoginTargetPathTest extends AppWebTestCase
{
    public function testTheLoginLeadsBackToThePageItCameFrom(): void
    {
        $client = $this->createClientFromAnIpOfItsOwn();
        $email = $this->createMember();

        $this->login($client, $email, '/login?_target_path=' . rawurlencode('/toulouse/soiree/concert--42'));

        self::assertResponseRedirects('/toulouse/soiree/concert--42');
    }

    public function testTheLoginKeepsTheFragmentThatRecordsTheClickMadeBeforeIt(): void
    {
        $client = $this->createClientFromAnIpOfItsOwn();
        $email = $this->createMember();

        $this->login($client, $email, '/login?_target_path=' . rawurlencode('/toulouse/soiree/concert--42#participer'));

        self::assertResponseRedirects('/toulouse/soiree/concert--42#participer');
    }

    public function testTheNextLoginGoesToThePersonalSpaceAgain(): void
    {
        $client = $this->createClientFromAnIpOfItsOwn();
        $email = $this->createMember();
        $this->login($client, $email, '/login?_target_path=' . rawurlencode('/toulouse/soiree/concert--42'));

        $this->login($client, $email, '/login');

        self::assertResponseRedirects('/espace-perso/mes-soirees');
    }

    public function testWithoutAPageTheLoginGoesToThePersonalSpace(): void
    {
        $client = $this->createClientFromAnIpOfItsOwn();
        $email = $this->createMember();

        $this->login($client, $email, '/login');

        self::assertResponseRedirects('/espace-perso/mes-soirees');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideForeignTargets(): iterable
    {
        yield 'another host' => ['https://evil.example/'];
        yield 'a protocol-relative URL' => ['//evil.example/'];
        yield 'a backslash browsers read as a slash' => ['/\\evil.example/'];
        yield 'a relative path' => ['evil'];
    }

    #[DataProvider('provideForeignTargets')]
    public function testTheLoginNeverLeadsOutOfTheSite(string $target): void
    {
        $client = $this->createClientFromAnIpOfItsOwn();
        $email = $this->createMember();

        $this->login($client, $email, '/login?_target_path=' . rawurlencode($target));

        self::assertResponseRedirects('/espace-perso/mes-soirees');
    }

    private function createClientFromAnIpOfItsOwn(): KernelBrowser
    {
        // Its own address: LoginThrottlingTest's failures must not hold these logins back
        $client = self::createClient();
        $client->setServerParameter('REMOTE_ADDR', \sprintf('10.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(1, 254)));

        return $client;
    }

    private function createMember(): string
    {
        $user = UserFactory::createOne(['verified' => true]);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'le-mot-de-passe'));
        save($user);

        return (string) $user->getEmail();
    }

    private function login(KernelBrowser $client, string $email, string $loginUrl): void
    {
        $crawler = $client->request('GET', $loginUrl);
        $form = $crawler->filter('form[action="/login"]')->form();
        $form['username'] = $email;
        $form['password'] = 'le-mot-de-passe';
        $client->submit($form);
    }
}
