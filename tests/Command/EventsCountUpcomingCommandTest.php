<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\App\Location;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function Zenstruck\Foundry\Persistence\refresh;

final class EventsCountUpcomingCommandTest extends AppKernelTestCase
{
    public function testTheCountsAreStoredAndTheWrittenRowsReported(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->many(2)->create(['place' => $place, 'category' => TagFactory::createOne(), 'agendaTypes' => ['concert']]);

        $tester = new CommandTester(new Application(self::$kernel)->find('app:events:count-upcoming'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/^\s*1\s+1\s+1\s+2\s+2\s*$/m', $tester->getDisplay());
        self::assertSame(2, refresh($toulouse)->getUpcomingEvents());
        self::assertSame(['concert' => 2], self::getContainer()->get(EventRepository::class)->findUpcomingAgendaTypes(new Location()->setCity($toulouse)));
    }
}
