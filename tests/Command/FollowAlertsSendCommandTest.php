<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Factory\FollowFactory;
use App\Factory\UserFactory;
use App\Message\SendFollowAlert;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class FollowAlertsSendCommandTest extends AppKernelTestCase
{
    use ClockSensitiveTrait;

    public function testEachMemberFollowingSomeoneIsQueuedOnceADay(): void
    {
        self::mockTime('2026-11-26 17:00');
        $follower = UserFactory::createOne();
        FollowFactory::createOne(['user' => $follower]);
        FollowFactory::createOne(['user' => UserFactory::createOne(['followAlertsSentAt' => new DateTimeImmutable('2026-11-26 08:00')])]);
        FollowFactory::createOne(['user' => UserFactory::createOne(['followAlerts' => false])]);
        FollowFactory::createOne(['user' => UserFactory::createOne(['enabled' => false])]);
        FollowFactory::createOne(['user' => UserFactory::createOne(['email' => 'ada@twitter.fr'])]);
        UserFactory::createOne();

        $tester = new CommandTester(new Application(self::$kernel)->find('app:follows:send-alerts'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 member(s) queued.', $tester->getDisplay());
        $messages = array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $this->transport()->getSent());
        self::assertCount(1, $messages, 'Not the one told today, nor who turned them off, nor a disabled account, nor an address that would not reach them, nor who follows no one');
        self::assertInstanceOf(SendFollowAlert::class, $messages[0]);
        self::assertSame($follower->getId(), $messages[0]->userId);
        self::assertEquals(new DateTimeImmutable('2026-11-26 17:00'), $messages[0]->until);
    }

    private function transport(): InMemoryTransport
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');

        return $transport;
    }
}
