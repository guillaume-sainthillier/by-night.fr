<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Elasticsearch\Message\ReplaceManyDocuments;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Factory\UserFactory;
use App\Tests\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class TagsMergeDuplicatesCommandTest extends AppKernelTestCase
{
    private EntityManagerInterface $entityManager;

    private int $oldestId;

    private int $keeperId;

    private int $categorisedByOldestId;

    private int $themedByBothId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // The command exists to clean data that predates the unique key on tag.name, so
        // the fixtures are written without it. SQLite DDL is transactional: the test
        // transaction restores the index afterwards.
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement($connection->getDatabasePlatform()->getDropIndexSQL('tag_name_unique', 'tag'));

        $country = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        $city = CityFactory::createOne(['name' => 'Toulouse', 'country' => $country]);
        $venue = ['place' => PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $city, 'country' => $country]), 'user' => UserFactory::createOne()];

        // Three tags with the same name. The keeper is the most used one, not the oldest.
        $oldest = TagFactory::createOne(['name' => 'Concert']);
        $keeper = TagFactory::createOne(['name' => 'Concert']);
        TagFactory::createOne(['name' => 'Concert']);
        $this->oldestId = (int) $oldest->getId();
        $this->keeperId = (int) $keeper->getId();

        $this->categorisedByOldestId = (int) EventFactory::createOne(['category' => $oldest, 'themes' => [], ...$venue])->getId();
        // Both spellings as themes: the merge must not produce a duplicate (event, tag) pair
        $this->themedByBothId = (int) EventFactory::createOne(['category' => $keeper, 'themes' => [$oldest, $keeper], ...$venue])->getId();
        EventFactory::createOne(['category' => null, 'themes' => [$keeper], ...$venue]);

        $this->entityManager->clear();
    }

    public function testPreviewChangesNothing(): void
    {
        $tester = $this->executeMerge([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString(\sprintf('keep #%d "Concert" (3 events)', $this->keeperId), $display);
        $this->assertStringContainsString(\sprintf('#%d "Concert" (2 events)', $this->oldestId), $display);
        $this->assertStringContainsString('Preview only', $display);

        $this->assertSame(3, TagFactory::count());
        $this->assertSame($this->oldestId, EventFactory::find(['id' => $this->categorisedByOldestId])->getCategory()?->getId());
    }

    public function testApplyMergesIntoTheMostUsedTagAndReindexesTheEventsItTouched(): void
    {
        $tester = $this->executeMerge(['--apply' => true]);
        $this->assertStringContainsString('Merges written', $tester->getDisplay());

        $this->entityManager->clear();

        // One tag left, the most used one
        $this->assertSame(1, TagFactory::count());
        $this->assertSame($this->keeperId, TagFactory::find(['name' => 'Concert'])->getId());

        // Categories and themes re-pointed, with a single (event, tag) pair where both spellings were themes
        $this->assertSame($this->keeperId, EventFactory::find(['id' => $this->categorisedByOldestId])->getCategory()?->getId());
        $themes = EventFactory::find(['id' => $this->themedByBothId])->getThemes();
        $this->assertCount(1, $themes);
        $this->assertSame($this->keeperId, $themes->first()->getId());
        $this->assertSame(2, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM event_tag'));

        // The two events that changed get their search document refreshed, the third does not
        $transport = self::getContainer()->get('messenger.transport.async');
        $this->assertInstanceOf(InMemoryTransport::class, $transport);
        $reindexed = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            $this->assertInstanceOf(ReplaceManyDocuments::class, $message);
            $reindexed = [...$reindexed, ...$message->getEntityIds()];
        }
        sort($reindexed);
        $expected = [$this->categorisedByOldestId, $this->themedByBothId];
        sort($expected);
        $this->assertSame($expected, $reindexed);

        // Idempotent
        $this->assertStringContainsString('Every tag name is unique', $this->executeMerge(['--apply' => true])->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function executeMerge(array $input): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:tags:merge-duplicates'));
        $tester->execute($input);
        $tester->assertCommandIsSuccessful();

        return $tester;
    }
}
