<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Enum\EventExportStatus;
use App\Enum\EventImportStatus;
use App\Factory\EventExportFactory;
use App\Factory\EventImportFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function Zenstruck\Foundry\Persistence\refresh;

final class TransfersCleanupCommandTest extends AppKernelTestCase
{
    public function testAJobThatStoppedMovingIsFailedForItsMemberToResume(): void
    {
        $checking = EventImportFactory::createOne(['status' => EventImportStatus::Checking, 'updatedAt' => new DateTimeImmutable('-3 hours')]);
        $importing = EventImportFactory::createOne(['status' => EventImportStatus::Importing, 'updatedAt' => new DateTimeImmutable('-3 hours')]);
        $moving = EventImportFactory::createOne(['status' => EventImportStatus::Importing]);
        $export = EventExportFactory::createOne(['status' => EventExportStatus::InProgress, 'updatedAt' => new DateTimeImmutable('-3 hours')]);

        $this->execute();

        foreach ([$checking, $importing, $moving, $export] as $job) {
            refresh($job);
        }
        self::assertSame(EventImportStatus::CheckFailed, $checking->getStatus());
        self::assertSame(EventImportStatus::Failed, $importing->getStatus());
        self::assertSame(EventImportStatus::Importing, $moving->getStatus());
        self::assertSame(EventExportStatus::Failed, $export->getStatus());
    }

    public function testOldJobsAreDeletedWithTheirFiles(): void
    {
        /** @var FilesystemOperator $storage */
        $storage = self::getContainer()->get('transfers.storage');
        $storage->write('imports/old/original.csv', 'Nom');
        $storage->write('imports/old/erreurs.csv', 'Nom;Erreurs');
        $storage->write('imports/recent/original.csv', 'Nom');
        $old = EventImportFactory::createOne(['status' => EventImportStatus::Completed, 'filePath' => 'imports/old/original.csv', 'createdAt' => new DateTimeImmutable('-31 days')]);
        EventImportFactory::createOne(['status' => EventImportStatus::Completed, 'filePath' => 'imports/recent/original.csv']);
        $oldId = $old->getId();

        $this->execute();

        self::assertSame(0, EventImportFactory::count(['id' => $oldId]));
        self::assertSame(1, EventImportFactory::count());
        self::assertFalse($storage->fileExists('imports/old/erreurs.csv'));
        self::assertTrue($storage->fileExists('imports/recent/original.csv'));
    }

    private function execute(): void
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:transfers:cleanup'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
    }
}
