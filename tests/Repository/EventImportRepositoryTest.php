<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Repository;

use App\Enum\EventImportStatus;
use App\Factory\EventImportFactory;
use App\Repository\EventImportRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

use function Zenstruck\Foundry\Persistence\refresh;

final class EventImportRepositoryTest extends AppKernelTestCase
{
    public function testOnlyOneOfTwoRequestsStartsTheImport(): void
    {
        $import = EventImportFactory::createOne(['status' => EventImportStatus::Checked]);
        $repository = self::getContainer()->get(EventImportRepository::class);

        self::assertTrue($repository->transition($import, [EventImportStatus::Checked], EventImportStatus::Importing));
        self::assertFalse($repository->transition($import, [EventImportStatus::Checked], EventImportStatus::Importing));

        refresh($import);
        self::assertSame(EventImportStatus::Importing, $import->getStatus());
    }

    public function testAnImportLeftRunningWithoutNewsIsStuck(): void
    {
        $stuck = EventImportFactory::createOne(['status' => EventImportStatus::Importing, 'updatedAt' => new DateTimeImmutable('-3 hours')]);
        EventImportFactory::createOne(['status' => EventImportStatus::Importing]);
        EventImportFactory::createOne(['status' => EventImportStatus::Completed, 'updatedAt' => new DateTimeImmutable('-3 hours')]);

        $found = self::getContainer()->get(EventImportRepository::class)->findStuckSince(new DateTimeImmutable('-2 hours'));

        self::assertSame([$stuck->getId()], array_map(static fn ($import) => $import->getId(), $found));
    }
}
