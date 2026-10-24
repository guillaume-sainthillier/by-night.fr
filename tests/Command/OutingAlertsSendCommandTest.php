<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Enum\EventStatus;
use App\Factory\EventFactory;
use App\Factory\UserEventFactory;
use App\Outing\OutingState;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;

use function Zenstruck\Foundry\Persistence\save;

final class OutingAlertsSendCommandTest extends AppKernelTestCase
{
    use ClockSensitiveTrait;

    #[RequiresPhpExtension('mjml')]
    public function testTheMembersGoingToAChangedEventAreTold(): void
    {
        $this->cancelledOuting();

        $tester = $this->tester();
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 alert(s) sent.', $tester->getDisplay());
        self::assertEmailCount(1);
    }

    public function testARunStartedWhileAnotherRunsStops(): void
    {
        $this->cancelledOuting();
        $running = self::getContainer()->get(LockFactory::class)->createLock('app:outing-alerts:send');
        self::assertTrue($running->acquire());

        $tester = $this->tester();
        $tester->execute([]);
        $running->release();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Another run is telling the changes', $tester->getDisplay());
        self::assertEmailCount(0);
    }

    /**
     * A member going to a concert cancelled 20 minutes ago.
     */
    private function cancelledOuting(): void
    {
        $concert = EventFactory::new()->withDates(new DateTimeImmutable('+5 days'))->create();
        UserEventFactory::createOne(['event' => $concert, 'going' => true, 'knownState' => OutingState::fromEvent($concert)]);

        self::mockTime('-20 minutes');
        $concert->setStatus(EventStatus::Cancelled);
        save($concert);
        self::mockTime(false);
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::$kernel)->find('app:outing-alerts:send'));
    }
}
