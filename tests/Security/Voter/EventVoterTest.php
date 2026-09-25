<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use App\Factory\EventFactory;
use App\Factory\UserFactory;
use App\Security\Voter\EventVoter;
use App\Tests\AppKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Checked through the authorization checker, so that the role hierarchy and the other
 * voters take part as they do in the personal space and the event API.
 */
final class EventVoterTest extends AppKernelTestCase
{
    public function testAVerifiedMemberCanCreateAnEvent(): void
    {
        self::assertTrue($this->isGranted(UserFactory::createOne(['verified' => true]), EventVoter::CREATE));
    }

    public function testAnUnverifiedMemberCannotCreateAnEvent(): void
    {
        self::assertFalse($this->isGranted(UserFactory::createOne(['verified' => false]), EventVoter::CREATE));
    }

    public function testADisabledMemberCannotCreateAnEvent(): void
    {
        self::assertFalse($this->isGranted(UserFactory::createOne(['verified' => true, 'enabled' => false]), EventVoter::CREATE));
    }

    public function testAnAdministratorCanCreateAnEventWithoutAVerifiedAddress(): void
    {
        self::assertTrue($this->isGranted(UserFactory::createOne(['verified' => false, 'roles' => ['ROLE_ADMIN']]), EventVoter::CREATE));
    }

    public function testAVisitorCannotCreateAnEvent(): void
    {
        self::assertFalse($this->isGranted(null, EventVoter::CREATE));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideOwnerAttributes(): iterable
    {
        yield 'edit' => [EventVoter::EDIT];
        yield 'delete' => [EventVoter::DELETE];
    }

    #[DataProvider('provideOwnerAttributes')]
    public function testTheOwnerCanChangeTheirEvent(string $attribute): void
    {
        $event = EventFactory::createOne();

        self::assertTrue($this->isGranted($event->getUser(), $attribute, $event));
    }

    #[DataProvider('provideOwnerAttributes')]
    public function testAnotherMemberCannotChangeTheEvent(string $attribute): void
    {
        $event = EventFactory::createOne();

        self::assertFalse($this->isGranted(UserFactory::createOne(['verified' => true]), $attribute, $event));
    }

    #[DataProvider('provideOwnerAttributes')]
    public function testNoMemberOwnsAnImportedEvent(string $attribute): void
    {
        $event = EventFactory::createOne(['user' => null, 'externalId' => 'oa-1', 'externalOrigin' => 'openagenda']);

        self::assertFalse($this->isGranted(UserFactory::createOne(['verified' => true]), $attribute, $event));
    }

    #[DataProvider('provideOwnerAttributes')]
    public function testAnAdministratorCanChangeAnyEvent(string $attribute): void
    {
        $event = EventFactory::createOne();

        self::assertTrue($this->isGranted(UserFactory::createOne(['roles' => ['ROLE_ADMIN']]), $attribute, $event));
    }

    #[DataProvider('provideOwnerAttributes')]
    public function testAVisitorCannotChangeAnEvent(string $attribute): void
    {
        self::assertFalse($this->isGranted(null, $attribute, EventFactory::createOne()));
    }

    public function testTheVoterAbstainsOnOtherAttributes(): void
    {
        self::assertFalse($this->isGranted(UserFactory::createOne(), 'event.publish', EventFactory::createOne()));
    }

    private function isGranted(?User $user, string $attribute, ?Event $event = null): bool
    {
        $tokenStorage = self::getContainer()->get('security.token_storage');
        $tokenStorage->setToken(null === $user ? null : new UsernamePasswordToken($user, 'main', $user->getRoles()));

        return self::getContainer()->get(AuthorizationCheckerInterface::class)->isGranted($attribute, $event);
    }
}
