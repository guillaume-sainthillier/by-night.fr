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
use App\Repository\UserRepository;
use App\Security\OAuthDataProvider;
use App\Security\UnverifiedAccountClaim;
use App\Security\UserSocialAuthenticator;
use App\Social\SocialProvider;
use App\Tests\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

/**
 * Signing in with a social network finds the account of the address or of the network
 * account, or opens one named after the person.
 */
final class UserSocialAuthenticatorTest extends AppKernelTestCase
{
    public function testANewcomerGetsAnAccountNamedAfterThem(): void
    {
        $user = $this->signIn(['sub' => 'google-1', 'name' => 'Camille Martin', 'email' => 'camille.martin@example.com']);

        self::assertSame('Camille Martin', $user->getUsername());
        self::assertSame('camille.martin@example.com', $user->getEmail());
        self::assertTrue($user->isVerified());
        self::assertSame(1, UserFactory::count(['email' => 'camille.martin@example.com']));
    }

    /**
     * The username is unique: the second Camille Martin to arrive used to break the sign-in
     * on the constraint, the free name being looked up among the e-mail addresses.
     */
    public function testANewcomerWhoseNameIsTakenGetsAFreeOne(): void
    {
        UserFactory::createOne(['username' => 'Camille Martin']);

        $user = $this->signIn(['sub' => 'google-2', 'name' => 'Camille Martin', 'email' => 'camille.m@example.com']);

        self::assertSame('Camille Martin-1', $user->getUsername());
    }

    /**
     * The free name was looked up among the usernames under the e-mail, which never changes:
     * a member named after the newcomer's address kept the loop going until memory ran out.
     */
    public function testAMemberNamedAfterTheAddressOfTheNewcomerDoesNotBlockTheirSignIn(): void
    {
        UserFactory::createOne(['username' => 'dominique@example.com', 'email' => 'someone-else@example.com']);

        $user = $this->signIn(['sub' => 'google-3', 'name' => 'Dominique', 'email' => 'dominique@example.com']);

        self::assertSame('Dominique', $user->getUsername());
    }

    /**
     * The username is public (profile URL, comments, search): it never shows the address.
     */
    public function testANewcomerWithoutANameIsNotNamedAfterTheirAddress(): void
    {
        UserFactory::createOne(['username' => 'Membre']);

        $user = $this->signIn(['sub' => 'google-5', 'name' => '', 'email' => 'anonymous@example.com']);

        self::assertSame('Membre-1', $user->getUsername());
    }

    public function testAMemberOfTheSameAddressIsSignedIn(): void
    {
        $member = UserFactory::createOne(['email' => 'lou@example.com', 'verified' => true]);

        $user = $this->signIn(['sub' => 'google-4', 'name' => 'Lou', 'email' => 'lou@example.com']);

        self::assertSame($member->getId(), $user->getId());
        self::assertSame('google-4', $user->getOAuth()?->getGoogleId());
    }

    /**
     * @param array<string, string> $googleProfile
     */
    private function signIn(array $googleProfile): User
    {
        $client = $this->createStub(OAuth2ClientInterface::class);
        $client->method('getAccessToken')->willReturn(new AccessToken(['access_token' => 'token']));
        $client->method('fetchUserFromToken')->willReturn(new GoogleUser($googleProfile));

        $clientRegistry = $this->createStub(ClientRegistry::class);
        $clientRegistry->method('getClient')->willReturn($client);

        $container = self::getContainer();
        $authenticator = new UserSocialAuthenticator(
            $container->get(Security::class),
            $clientRegistry,
            $container->get(EntityManagerInterface::class),
            $container->get(UrlGeneratorInterface::class),
            $container->get(UserAuthenticatorInterface::class),
            $container->get(SocialProvider::class),
            new OAuthDataProvider($clientRegistry),
            $container->get(UserRepository::class),
            $container->get(UnverifiedAccountClaim::class),
        );

        $request = Request::create('/login-social/check/google');
        $request->attributes->set('service', 'google');

        $user = $authenticator->authenticate($request)->getUser();
        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
