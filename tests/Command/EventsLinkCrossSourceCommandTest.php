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
use App\Factory\CrossSourceLinkFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Parser\Common\CDiscountAwinParser;
use App\Parser\Common\FnacSpectaclesAwinParser;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class EventsLinkCrossSourceCommandTest extends AppKernelTestCase
{
    public function testItPreviewsThenLinks(): void
    {
        $city = CityFactory::toulouse()->create();
        $venue = PlaceFactory::createOne(['name' => 'Zénith', 'city' => $city, 'country' => $city->getCountry()]);
        $date = new DateTimeImmutable('+10 days');
        $fnac = EventFactory::createOne(['fromData' => FnacSpectaclesAwinParser::getParserName(), 'name' => 'Orelsan - Tournée', 'place' => $venue, 'startDate' => $date, 'endDate' => $date]);
        $cdiscount = EventFactory::createOne(['fromData' => CDiscountAwinParser::getParserName(), 'name' => 'Orelsan', 'place' => $venue, 'startDate' => $date, 'endDate' => $date]);

        $tester = new CommandTester(new Application(self::$kernel)->find('app:events:link-cross-source'));
        $tester->execute(['--place' => [(string) $venue->getId()]]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Preview only', $tester->getDisplay());
        self::assertSame(0, CrossSourceLinkFactory::count());

        $tester->execute(['--place' => [(string) $venue->getId()], '--apply' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 link(s) made, 0 taken back.', $tester->getDisplay());
        self::assertSame($fnac->getId(), EventFactory::find(['id' => $cdiscount->getId()])->getDuplicateOf()?->getId());
    }
}
