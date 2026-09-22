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
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\PlaceMetadataFactory;
use App\Factory\PlaceNameSlugFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Places left behind once their events are gone are removed with their identities and
 * name slugs; places with events, and places too young to be trusted event-less, stay.
 */
final class PlacesRemoveEventlessCommandTest extends AppKernelTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $country = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        $city = CityFactory::createOne(['name' => 'Amiens', 'country' => $country]);
        $location = ['city' => $city, 'country' => $country];
        $longAgo = new DateTimeImmutable('2024-01-01');

        // Still hosts an event
        EventFactory::createOne(['place' => PlaceFactory::createOne(['name' => 'Le Bikini', 'createdAt' => $longAgo, ...$location])]);

        // A town-level place whose events all moved to venue-level ones
        $leftBehind = PlaceFactory::createOne(['name' => 'Amiens', 'street' => 'Place Notre-Dame', 'createdAt' => $longAgo, ...$location]);
        PlaceMetadataFactory::createOne(['place' => $leftBehind, 'externalId' => 'DT-town', 'externalOrigin' => 'datatourisme']);
        PlaceNameSlugFactory::createOne(['place' => $leftBehind, 'slug' => 'amiens', 'city' => $city, 'country' => $country]);

        // Left behind by another source
        $otherSource = PlaceFactory::createOne(['name' => 'Ancienne salle', 'createdAt' => $longAgo, ...$location]);
        PlaceMetadataFactory::createOne(['place' => $otherSource, 'externalId' => 'oa-9', 'externalOrigin' => 'openagenda']);

        // Just created: its events may still be on their way
        PlaceFactory::createOne(['name' => 'Salle toute neuve', 'createdAt' => new DateTimeImmutable(), ...$location]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
    }

    public function testPreviewListsWithoutDeleting(): void
    {
        $display = $this->doRunCommand([])->getDisplay();

        self::assertStringContainsString('Previewing 2 event-less place(s)', $display);
        self::assertStringContainsString('Amiens', $display);
        self::assertStringContainsString('Ancienne salle', $display);
        self::assertStringContainsString('Preview only', $display);

        self::assertSame(4, PlaceFactory::count());
        self::assertSame(2, PlaceMetadataFactory::count());
        self::assertSame(1, PlaceNameSlugFactory::count());
    }

    public function testApplyDeletesThePlacesLeftBehindWithTheirIdentitiesAndSlugs(): void
    {
        $display = $this->doRunCommand(['--apply' => true])->getDisplay();

        self::assertStringContainsString('2 place(s) removed', $display);
        self::assertSame(['Le Bikini', 'Salle toute neuve'], $this->placeNames());
        self::assertSame(0, PlaceMetadataFactory::count());
        self::assertSame(0, PlaceNameSlugFactory::count());
        self::assertSame(1, EventFactory::count());

        // Idempotent: a second run finds nothing to do
        self::assertStringContainsString('No event-less place', $this->doRunCommand(['--apply' => true])->getDisplay());
    }

    public function testOriginKeepsThePlacesOfTheOtherSources(): void
    {
        $this->doRunCommand(['--apply' => true, '--origin' => 'datatourisme']);

        self::assertSame(['Ancienne salle', 'Le Bikini', 'Salle toute neuve'], $this->placeNames());
        self::assertSame(1, PlaceMetadataFactory::count(['externalOrigin' => 'openagenda']));
    }

    public function testMinAgeDecidesWhetherARecentPlaceGoesToo(): void
    {
        $this->doRunCommand(['--apply' => true, '--min-age' => '0 second']);

        self::assertSame(['Le Bikini'], $this->placeNames());
    }

    public function testAnUnreadableMinAgeIsRefused(): void
    {
        $tester = $this->doRunCommand(['--min-age' => 'soon'], expectSuccess: false);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('not a relative date', $tester->getDisplay());
        self::assertSame(4, PlaceFactory::count());
    }

    /**
     * @return list<string|null>
     */
    private function placeNames(): array
    {
        $names = array_map(static fn ($place): ?string => $place->getName(), PlaceFactory::all());
        sort($names);

        return $names;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input, bool $expectSuccess = true): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:places:remove-eventless'));
        $tester->execute($input);
        if ($expectSuccess) {
            $tester->assertCommandIsSuccessful();
        }

        return $tester;
    }
}
