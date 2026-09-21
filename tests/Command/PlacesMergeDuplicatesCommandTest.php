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
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\PlaceMetadataFactory;
use App\Factory\PlaceNameSlugFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class PlacesMergeDuplicatesCommandTest extends AppKernelTestCase
{
    private const string IDENTITY = 'dt-venue-42';

    private const string ORIGIN = 'datatourisme';

    private EntityManagerInterface $entityManager;

    private Place $keeper;

    private Place $olderButQuieter;

    private Place $orphan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // The command exists to clean data that predates the unique key on place_metadata,
        // so the fixtures below have to be written without it. SQLite DDL is transactional:
        // the test transaction restores the index afterwards.
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement($connection->getDatabasePlatform()->getDropIndexSQL('place_metadata_external_id_unique', 'place_metadata'));

        $country = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        $city = CityFactory::createOne(['name' => 'Toulouse', 'country' => $country]);
        $location = ['city' => $city, 'country' => $country];

        // Three places share one external identity. The keeper is the one with the most
        // events, even though another one is older.
        $this->olderButQuieter = PlaceFactory::createOne(['name' => 'Bikini', 'createdAt' => new DateTimeImmutable('2018-01-01'), ...$location]);
        $this->keeper = PlaceFactory::createOne(['name' => 'Le Bikini', 'createdAt' => new DateTimeImmutable('2020-01-01'), ...$location]);
        $this->orphan = PlaceFactory::createOne(['name' => 'Le Bikini (Ramonville)', 'createdAt' => new DateTimeImmutable('2022-07-01'), ...$location]);

        foreach ([$this->olderButQuieter, $this->keeper, $this->orphan] as $place) {
            PlaceMetadataFactory::createOne(['place' => $place, 'externalId' => self::IDENTITY, 'externalOrigin' => self::ORIGIN]);
        }

        // The same identity recorded twice on the keeper, and an identity only the loser knows
        PlaceMetadataFactory::createOne(['place' => $this->keeper, 'externalId' => self::IDENTITY, 'externalOrigin' => self::ORIGIN]);
        PlaceMetadataFactory::createOne(['place' => $this->olderButQuieter, 'externalId' => 'oa-location-7', 'externalOrigin' => 'openagenda']);

        // One slug the keeper already has, one it lacks
        PlaceNameSlugFactory::createOne(['place' => $this->keeper, 'slug' => 'bikini', 'city' => $city, 'country' => $country]);
        PlaceNameSlugFactory::createOne(['place' => $this->olderButQuieter, 'slug' => 'bikini', 'city' => $city, 'country' => $country]);
        PlaceNameSlugFactory::createOne(['place' => $this->olderButQuieter, 'slug' => 'bikini ramonville', 'city' => $city, 'country' => $country]);

        EventFactory::createMany(2, ['place' => $this->keeper]);
        EventFactory::createOne(['place' => $this->olderButQuieter]);

        // Collections built above are stale in the identity map; the command must load fresh objects
        $this->entityManager->clear();
    }

    public function testPreviewChangesNothing(): void
    {
        $tester = $this->runCommand([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString(\sprintf('keep #%d "Le Bikini" (2 events)', $this->keeper->getId()), $display);
        $this->assertStringContainsString(\sprintf('remove #%d "Bikini" (1 events)', $this->olderButQuieter->getId()), $display);
        $this->assertStringContainsString('drop 1 redundant row(s)', $display);
        $this->assertStringContainsString('Preview only', $display);

        $this->assertSame(3, PlaceFactory::count());
        $this->assertSame(4, PlaceMetadataFactory::count(['externalId' => self::IDENTITY]));
        $this->assertSame(1, EventFactory::count(['place' => $this->olderButQuieter]));
    }

    public function testApplyMergesEverythingIntoThePlaceWithTheMostEvents(): void
    {
        $tester = $this->runCommand(['--apply' => true]);

        $this->assertStringContainsString('Merges written', $tester->getDisplay());

        // One place left, the one that had the most events
        $this->assertSame(1, PlaceFactory::count());
        $keeper = PlaceFactory::find(['name' => 'Le Bikini']);
        $this->assertSame($this->keeper->getId(), $keeper->getId());

        // Every event now points to it
        $this->assertSame(3, EventFactory::count(['place' => $keeper]));

        // The shared identity is down to one row, and the loser's own identity moved over
        $this->assertSame(1, PlaceMetadataFactory::count(['externalId' => self::IDENTITY]));
        $this->assertSame(1, PlaceMetadataFactory::count(['externalId' => 'oa-location-7', 'place' => $keeper]));
        $this->assertSame(2, PlaceMetadataFactory::count());

        // Known slug dropped with the loser, unknown slug moved
        $slugs = array_map(static fn ($nameSlug): ?string => $nameSlug->getSlug(), PlaceNameSlugFactory::findBy(['place' => $keeper]));
        sort($slugs);
        $this->assertSame(['bikini', 'bikini ramonville'], $slugs);
        $this->assertSame(2, PlaceNameSlugFactory::count());

        // Idempotent: a second run finds nothing to do
        $this->assertStringContainsString('points to a single place', $this->runCommand(['--apply' => true])->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(array $input): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:places:merge-duplicates'));
        $tester->execute($input);
        $tester->assertCommandIsSuccessful();

        return $tester;
    }
}
