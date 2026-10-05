<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Entity\Place;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\ParserDataFactory;
use App\Factory\PlaceFactory;
use App\Reject\Reject;
use App\Tests\AppKernelTestCase;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The events whose place has no country are deleted, with their families; their sources may
 * bring them back once corrected.
 */
final class EventsRemoveCountrylessCommandTest extends AppKernelTestCase
{
    private Place $abroad;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->abroad = PlaceFactory::createOne(['name' => 'PortAventura World', 'cityName' => 'Vila-seca', 'city' => null, 'country' => null]);
    }

    public function testTheEventsWithoutCountryAreDeletedWithTheirFamilies(): void
    {
        $canonical = EventFactory::createOne(['place' => $this->abroad, 'externalOrigin' => 'awin.fnac', 'externalId' => '1001']);
        $duplicate = EventFactory::createOne(['place' => $this->abroad, 'externalOrigin' => 'awin.fnac', 'externalId' => '1002', 'duplicateOf' => $canonical]);
        $inFrance = EventFactory::createOne(['place' => PlaceFactory::createOne(['country' => CountryFactory::france()->create()])]);

        $display = $this->doRunCommand(['--apply' => true])->getDisplay();

        self::assertSame(0, EventFactory::count(['id' => [$canonical->getId(), $duplicate->getId()]]));
        self::assertSame(1, EventFactory::count(['id' => $inFrance->getId()]));
        self::assertSame(1, PlaceFactory::count(['id' => $this->abroad->getId()]), 'Left for app:places:remove-eventless');
        self::assertStringContainsString('2 event(s) removed', $display);
    }

    public function testTheirSourcesMayBringThemBack(): void
    {
        // A numeric id: bound as an integer, MySQL would compare it numerically
        EventFactory::createOne(['place' => $this->abroad, 'externalOrigin' => 'awin.fnac', 'externalId' => '1001']);
        $otherSource = ParserDataFactory::createOne(['externalOrigin' => 'openagenda', 'externalId' => '1001', 'reason' => Reject::EVENT_DELETED]);

        $this->doRunCommand(['--apply' => true]);

        $exploration = ParserDataFactory::find(['externalOrigin' => 'awin.fnac', 'externalId' => '1001']);
        self::assertSame(Reject::VALID | Reject::BAD_COUNTRY, $exploration->getReason(), 'Judged again when its source corrects it, not deleted by its creator');
        self::assertSame(Reject::EVENT_DELETED, ParserDataFactory::find(['id' => $otherSource->getId()])->getReason());
    }

    public function testAnEventADuplicateInACountryRedirectsToIsKept(): void
    {
        $canonical = EventFactory::createOne(['place' => $this->abroad]);
        $duplicate = EventFactory::createOne(['place' => PlaceFactory::createOne(['country' => CountryFactory::france()->create()]), 'duplicateOf' => $canonical]);

        $display = $this->doRunCommand(['--apply' => true])->getDisplay();

        self::assertSame(2, EventFactory::count(['id' => [$canonical->getId(), $duplicate->getId()]]));
        self::assertStringContainsString('1 event(s) kept', $display);
    }

    public function testThePreviewDeletesNothing(): void
    {
        $event = EventFactory::createOne(['place' => $this->abroad, 'name' => 'Halloween Party']);

        $display = $this->doRunCommand([])->getDisplay();

        self::assertSame(1, EventFactory::count(['id' => $event->getId()]));
        self::assertStringContainsString('Previewing 1 event(s)', $display);
        self::assertStringContainsString('Halloween Party', $display);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:events:remove-countryless'));
        $tester->execute($input);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester;
    }
}
