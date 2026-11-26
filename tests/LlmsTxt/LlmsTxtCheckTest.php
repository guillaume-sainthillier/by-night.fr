<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\LlmsTxt;

use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PageFactory;
use App\Factory\PlaceFactory;
use App\LlmsTxt\LlmsTxtLinkProvider;
use App\Tests\AppKernelTestCase;
use App\Tests\Stats\CountsUpcomingEvents;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The checks of debug:llms-txt on every section filled: no duplicate, relative or off-site URL, no empty title.
 */
final class LlmsTxtCheckTest extends AppKernelTestCase
{
    use CountsUpcomingEvents;

    public function testTheFileHasNoProblem(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        EventFactory::new()->withDates(new DateTimeImmutable('+2 days'))->many(LlmsTxtLinkProvider::CITY_MIN_EVENTS)->create(['place' => $place]);
        self::counter()->refresh();
        PageFactory::createOne(['title' => 'Est-ce que le handpan est illégal ?', 'content' => "<p>Non, le handpan n'est pas illégal.</p>"]);

        $tester = new CommandTester(new Application(self::$kernel)->find('debug:llms-txt'));
        $tester->execute(['--strict' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('No problem found', $tester->getDisplay());
    }
}
