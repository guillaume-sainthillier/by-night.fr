<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Factory\CityFactory;
use App\Factory\UserFactory;
use App\Message\SendWeekendDigest;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class DigestSendCommandTest extends AppKernelTestCase
{
    use ClockSensitiveTrait;

    public function testEachMemberWhoAskedForItIsQueuedForTheComingWeekend(): void
    {
        self::mockTime('2026-10-22 07:00');
        $toulouse = CityFactory::toulouse()->create();
        $subscriber = UserFactory::createOne(['city' => $toulouse, 'weekendDigest' => true]);
        UserFactory::createOne(['city' => $toulouse, 'weekendDigest' => true, 'weekendDigestSentWeek' => '2026-W43']);
        UserFactory::createOne(['city' => $toulouse, 'weekendDigest' => true, 'weekendDigestSentWeek' => '2026-W42']);
        UserFactory::createOne(['city' => $toulouse, 'weekendDigest' => true, 'enabled' => false]);
        UserFactory::createOne(['city' => $toulouse]);

        $tester = new CommandTester(new Application(self::$kernel)->find('app:digest:send'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('2 member(s) queued.', $tester->getDisplay());

        $messages = $this->queued();
        self::assertCount(2, $messages, 'Not the one sent this week, nor the disabled account, nor the one who never asked');
        self::assertSame($subscriber->getId(), $messages[0]->userId);
        self::assertEquals(new DateTimeImmutable('2026-10-23'), $messages[0]->from);
        self::assertEquals(new DateTimeImmutable('2026-10-25'), $messages[0]->to);
    }

    /**
     * @return list<SendWeekendDigest>
     */
    private function queued(): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');

        return array_values(array_map(
            static fn (Envelope $envelope): SendWeekendDigest => $envelope->getMessage(),
            array_filter([...$transport->getSent()], static fn (Envelope $envelope): bool => $envelope->getMessage() instanceof SendWeekendDigest),
        ));
    }
}
