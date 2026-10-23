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
use App\Security\TwoFactor\LoginTwoFactorCondition;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * The second factor is asked when someone logs in, not when a member already logged in is authenticated again (their
 * roles reloaded after a network is disconnected, a network connected from their account).
 */
final class LoginTwoFactorConditionTest extends TestCase
{
    public function testALoginWithoutSessionAsksForTheSecondFactor(): void
    {
        self::assertTrue($this->shouldAsk(null, $this->member('membre@example.com')));
    }

    public function testTheMemberAlreadyLoggedInIsNotAskedAgain(): void
    {
        $member = $this->member('membre@example.com');

        self::assertFalse($this->shouldAsk(new PostAuthenticationToken($member, 'main', ['ROLE_USER']), $member));
    }

    /**
     * Two members a network logged in without an address share an empty identifier: told apart by their id
     */
    public function testAnotherAccountWithoutAddressIsAsked(): void
    {
        $current = new PostAuthenticationToken($this->member(''), 'main', ['ROLE_USER']);

        self::assertTrue($this->shouldAsk($current, $this->member('')));
    }

    public function testAnotherAccountLoggingInOverASessionIsAsked(): void
    {
        $current = new PostAuthenticationToken($this->member('autre@example.com'), 'main', ['ROLE_USER']);

        self::assertTrue($this->shouldAsk($current, $this->member('membre@example.com')));
    }

    /**
     * Starting over from the login form while the second factor of the same account is pending
     */
    public function testALoginWaitingForItsSecondFactorIsAskedAgain(): void
    {
        $member = $this->member('membre@example.com');
        $pending = new TwoFactorToken(new PostAuthenticationToken($member, 'main', ['ROLE_USER']), null, 'main', ['google']);

        self::assertTrue($this->shouldAsk($pending, $member));
    }

    private function shouldAsk(?TokenInterface $current, User $loggingIn): bool
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($current);

        $context = $this->createStub(AuthenticationContextInterface::class);
        $context->method('getUser')->willReturn($loggingIn);

        return new LoginTwoFactorCondition($tokenStorage)->shouldPerformTwoFactorAuthentication($context);
    }

    private function member(string $email): User
    {
        static $id = 0;
        $member = new User()->setEmail($email);
        new ReflectionProperty(User::class, 'id')->setValue($member, ++$id);

        return $member;
    }
}
